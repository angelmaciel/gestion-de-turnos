# Pruebas de API y de carga

Los tests de Pest prueban el backend por dentro. Esta carpeta lo prueba desde
afuera, como un cliente real: contra la misma imagen Docker que se publica en
Render, por HTTP y sin tocar la base.

| Carpeta | Herramienta | Qué cubre |
|---|---|---|
| [`postman/`](postman) | Postman · Newman | 41 peticiones y 181 aserciones: funcionales, control de acceso, validaciones y seguridad |
| [`jmeter/`](jmeter) | Apache JMeter 5.6.3 | Carga de una jornada de atención con cinco perfiles en paralelo |

Las dos corren en el CI en cada push (job **API (Newman · JMeter)**), que
guarda los informes HTML como artefacto `informes-api`.

## Colección de Postman

Se importa en Postman tal cual: `postman/gestion-turnos.postman_collection.json`
con el entorno `postman/local.postman_environment.json`. Cada corrida crea sus
propios datos (cédula, especialidad), así que no depende del estado de la base
y se puede repetir.

| Carpeta | Casos |
|---|---|
| 01 Autenticación | Login de los cuatro roles; contraseña incorrecta y usuario inexistente devuelven **el mismo mensaje** (no se pueden enumerar cuentas); campos obligatorios; `/auth/me` con y sin token |
| 02 Control de acceso por rol | Cada rol recibe 403 fuera de lo suyo (mesa de entrada no crea especialidades, preconsulta no registra turnos, etc.); Torre de Control solo para admin; especialidad duplicada → 422 |
| 03 Validaciones | Cédula con puntos, nombre con números, especialidad inexistente, cuerpo vacío → 422 con el campo y el mensaje correctos |
| 04 Flujo completo | Mesa de entrada registra → preconsulta busca por cédula y carga signos vitales (IMC calculado) → el profesional llama → la pantalla pública muestra el llamado **sin la cédula** → atención finalizada. En el medio, las transiciones prohibidas: preconsulta dos veces, atender sin llamar, otra especialidad que llama, ausente sobre un turno atendido |
| 05 Seguridad | Los turnos no se recorren por número (ULID → 404); fuerza bruta: el sexto login fallido seguido recibe 429 |
| 06 Cierre de sesión | Después del logout, el mismo token devuelve 401 |

Además, toda respuesta se valida contra un tiempo máximo (`maxMs`, 1500 ms por
defecto) y contenido JSON.

```bash
# Levantar la app (la misma imagen de Render, con SQLite y datos de ejemplo)
docker build -t gestion-turnos-qa .
docker run -d --name turnos-qa -p 10000:10000 -e BROADCAST_CONNECTION=log -e SESSION_SECURE_COOKIE=false gestion-turnos-qa

# Correr la colección
npx -y newman@6.2.1 run qa/postman/gestion-turnos.postman_collection.json -e qa/postman/local.postman_environment.json
```

La colección hace 14 logins y el límite es de 20 por minuto por IP: para
repetirla, esperar un minuto o reiniciar el contenedor.

**Contraprueba.** Corrida con la contraseña mal configurada
(`--env-var password=otra`), fallan 70 aserciones y Newman sale con código 1:
las pruebas detectan errores, no pasan por defecto.

## Prueba de carga con JMeter

`jmeter/jornada.jmx` simula una jornada: cinco grupos de usuarios virtuales en
paralelo, cada uno con su rol, una pausa de 1 a 3 s entre acciones y un login
por usuario al empezar.

| Grupo | Acción en bucle |
|---|---|
| Mesa de entrada | Registra turnos (`POST /api/entrada/turnos`, cédula al azar) |
| Preconsulta | Consulta su cola |
| Profesional | Consulta la cola del consultorio |
| Admin | Consulta la Torre de Control |
| Pantalla de la sala | Consulta los llamados (pública, sin login) |

Se parametriza con `-J`: `usuarios` (por grupo), `rampa` y `duracion` en
segundos, `host` y `port`. El CI corre 10 usuarios por grupo (50 en total)
durante 60 s y falla si el **P95 total supera 500 ms o los errores superan el
1 %** ([`verificar-umbrales.mjs`](jmeter/verificar-umbrales.mjs)).

Todos los usuarios virtuales salen de una misma IP, así que la app se levanta
con los límites de peticiones altos (`config/limites.php`). Los valores de
producción no cambian: un test de Pest lo asegura.

```bash
docker network create qa-net
docker run -d --name turnos-qa --network qa-net -p 10000:10000 \
  -e BROADCAST_CONNECTION=log -e SESSION_SECURE_COOKIE=false \
  -e RATE_LIMIT_LOGIN=10000 -e RATE_LIMIT_LOGIN_IP=10000 \
  -e RATE_LIMIT_API=1000000 -e RATE_LIMIT_PUBLICO=1000000 gestion-turnos-qa

docker run --rm --network qa-net -v "$PWD/qa/jmeter:/plan:ro" -v "$PWD/qa/resultados:/out" \
  alpine/jmeter:5.6.3 -n -t /plan/jornada.jmx -Jhost=turnos-qa -Jusuarios=10 -Jduracion=60 \
  -l /out/jmeter.jtl -e -o /out/jmeter

node qa/jmeter/verificar-umbrales.mjs qa/resultados/jmeter/statistics.json 500 1
```

## Hallazgos

### 1. Altas de turno simultáneas fallaban con 500 (corregido)

Con 50 usuarios virtuales, el 0,8 % de las altas de turno devolvía **500**.
Con 125, el 6,7 %. Las lecturas nunca fallaban. El log mostraba
`SQLSTATE[HY000]: General error: 5 database is locked`.

**Causa.** La conexión SQLite no tenía `busy_timeout`: cuando dos escrituras
coincidían, la segunda fallaba al instante en vez de esperar. Afectaba al demo
de Render, que corre con SQLite.

**Arreglo** (`config/database.php`): `busy_timeout` de 5 s, modo WAL (se
puede leer mientras se escribe) y transacciones `IMMEDIATE` (toman el bloqueo
al empezar, donde la espera sí funciona). Todo configurable por variable de
entorno.

| Carga (60 s, Docker local) | | Errores | P95 total | Pet./s |
|---|---|---|---|---|
| 50 usuarios virtuales | antes | 0,2 % (0,8 % en altas) | 191 ms | 22,4 |
| | después | **0 %** | **123 ms** | 22,7 |
| 125 usuarios virtuales | antes | 1,3 % (6,7 % en altas) | 1.994 ms | 35,2 |
| | después | **0 %** | **1.303 ms** | 43,0 |

### 2. La imagen no arrancaba fuera de Render (corregido)

`entrypoint.sh` le daba a `PORT` el valor 10000 si venía vacío, pero no lo
exportaba. `envsubst` solo ve variables exportadas, así que nginx recibía
`listen ;` y no arrancaba. En Render no se notaba porque Render siempre define
`PORT`. Ahora se exporta.

### 3. Techo de rendimiento (abierto)

Con 125 usuarios virtuales la app se estabiliza en unas 43 peticiones por
segundo y la latencia sube a más de un segundo, sin errores. El cuello de
botella probable es PHP-FPM, que en la imagen oficial atiende con 5 procesos
(`pm.max_children = 5`). Antes de abrir a más de un centro conviene ajustar
ese valor a la memoria del servidor y repetir la prueba.
