# Notas de seguridad

Cómo funcionan las protecciones del sistema. Está acá porque varias no son
evidentes leyendo el código, y modificarlas sin entenderlas rompe cosas de forma
silenciosa.

## Dónde vive cada cosa

| Protección | Archivo |
|---|---|
| Límite de intentos de login y de la API | `app/Providers/AppServiceProvider.php` |
| Expiración de sesión | `config/sanctum.php` |
| Auditoría de accesos a datos clínicos | `app/Models/AuditLog.php` |
| Autorización por pertenencia | `app/Policies/` |
| Recorte de campos por rol | `app/Http/Resources/` |
| Cifrado en reposo e índice ciego | `app/Models/Patient.php`, `Appointment.php` |
| Bloqueos de concurrencia | `app/Services/` |
| Orígenes permitidos | `config/cors.php` |
| Cabeceras y bloqueo de archivos | `nginx/default.conf` |

## Lo que conviene entender antes de tocar

**La cédula está cifrada y no admite `WHERE`.** El cifrado de Laravel usa un IV
aleatorio: el mismo texto produce cifrados distintos cada vez. Por eso existe
`cedula_hash`, un HMAC determinista que permite buscar y garantizar unicidad sin
guardar el número en claro. Buscar con `where('cedula', ...)` **no va a fallar,
simplemente no va a encontrar nada**. Se usa el scope `Patient::conCedula()`.

**Los datos clínicos también están cifrados.** Se pudo hacer sin costo porque
ninguna consulta filtra ni ordena por peso, altura o presión. Si en el futuro
hace falta un reporte que agrupe por esos campos, hay que resolverlo con una
columna derivada, no quitando el cifrado.

**La pantalla de sala es pública.** Su endpoint y su canal de WebSocket no
requieren autenticación. `PublicCallResource` y `PacienteLlamado` recortan los
campos a propósito: no viaja la especialidad, porque permitiría inferir la
condición médica del paciente frente a toda la sala. Hay tests que fallan si
alguien agrega un campo de más.

**Las rutas resuelven por ULID.** El id autoincremental nunca se expone. Si se
agrega un endpoint nuevo, el modelo ya define `getRouteKeyName()`, pero una
consulta manual con `find($id)` sobre un valor que viene de la URL no va a
funcionar: hay que buscar por `ulid`.

**La sesión va en cookie `HttpOnly` con CSRF.** El grupo `api` incluye
`EnsureFrontendRequestsAreStateful`. **No** hay que excluir `api/*` de la
validación CSRF: con autenticación por cookie el navegador la adjunta solo, y sin
token cualquier sitio podría disparar peticiones en nombre del usuario.

**Llamar un turno y asignar el número del día usan `Cache::lock`.** Son los dos
puntos donde dos personas compiten por el mismo recurso. Los respaldan índices
únicos en la base, por si el lock expira o el sistema corre en varios nodos.

## La APP_KEY es irremplazable

Con el cifrado en reposo activo, la `APP_KEY` es la única llave que descifra los
datos clínicos y las cédulas. Si se pierde o se regenera:

- Los datos cifrados quedan **irrecuperables**.
- Los `cedula_hash` dejan de coincidir: los pacientes existentes se vuelven
  imposibles de encontrar y se duplicarían al registrarlos de nuevo.

Por lo tanto: **nunca** correr `php artisan key:generate` sobre una instalación
con datos, y guardar la llave junto con los backups. Un dump de la base **sin la
`APP_KEY` es un archivo inútil**.

## Verificación

Los tests cubren la autorización (un usuario ajeno recibe 403), el recorte del
canal público, el cifrado en reposo y la numeración de turnos.

```bash
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/phpstan analyse
```
