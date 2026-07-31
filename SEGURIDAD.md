# Seguridad — Gestión de Turnos

Este sistema maneja **datos de salud**: la categoría de dato personal con mayor
nivel de protección exigido. Este documento registra qué protecciones están
implementadas y qué falta hacer antes de exponerlo fuera de una PC de desarrollo.

---

## ✅ Implementado

| Protección | Dónde |
|---|---|
| Límite de intentos de login (5/min por email+IP, 20/min por IP) | `AppServiceProvider::configurarLimitesDePeticiones()` |
| Límite general de la API (120/min) y de la pantalla pública (30/min) | mismo archivo + `routes/api.php` |
| Expiración de tokens a las 8 h | `config/sanctum.php` → `SANCTUM_EXPIRATION` |
| Registro de auditoría de accesos a datos clínicos | tabla `audit_logs`, modelo `AuditLog` |
| CORS restringido al origen del frontend | `config/cors.php` ← `FRONTEND_URL` |
| Cabeceras de seguridad (anti-clickjacking, nosniff, no-store) | `nginx/default.conf` |
| Bloqueo web de `.env`, logs, `vendor/`, `composer.json` | `nginx/default.conf` |
| Política de contraseñas (12+, mayús/minús, números, símbolos) | `AppServiceProvider::configurarPoliticaDeContrasenas()` |
| MySQL y Redis solo accesibles desde `127.0.0.1` | `docker-compose.yml` |
| Redis con contraseña obligatoria | `docker-compose.yml` → `REDIS_PASSWORD` |
| Seeders de usuarios bloqueados en producción | `UserSeeder`, `ProfessionalSeeder` |
| Canal público sin datos sensibles (sin cédula, especialidad ni datos clínicos) | `PacienteLlamado`, `PublicScreenController` |
| Backups con rotación a 30 días | `docker/backup.sh` |
| Aislamiento por especialidad entre profesionales | `ProfessionalController` |
| **Cifrado en reposo de datos clínicos** (peso, altura, presión) | casts `encrypted` en `Appointment` |
| **Cifrado en reposo de la cédula** + índice ciego para buscarla | `Patient` |

Cubierto por tests en `tests/Feature/AppointmentFlowTest.php`, incluidos dos que
fallan si alguien vuelve a filtrar datos sensibles al canal público.

---

## ⛔ Obligatorio antes de salir a producción

- [ ] **`APP_DEBUG=false`** en el `.env` del servidor.
      Con `true`, cualquier error muestra el stack trace con las credenciales de la base.
- [ ] **`APP_ENV=production`**.
      Activa el forzado de HTTPS y bloquea los seeders de usuarios demo.
- [ ] **HTTPS con certificado válido** (Let's Encrypt es gratuito).
      Sin esto, contraseñas, tokens y nombres de pacientes viajan en texto plano.
- [ ] **Rotar todos los secretos**: `APP_KEY`, `DB_PASSWORD`, `MYSQL_ROOT_PASSWORD`,
      `REDIS_PASSWORD`, `REVERB_APP_SECRET` y la API key de Gemini.
- [ ] **Borrar los usuarios demo** (`*@example.com`) y crear cuentas reales
      con contraseñas que cumplan la política.
- [ ] **Programar el backup**: `0 2 * * * cd /ruta && bash docker/backup.sh`
      y verificar una restauración real. Un backup no probado no es un backup.
- [ ] **Guardar los backups fuera del servidor** (cifrados: contienen datos clínicos).

---

## ⚠️ Riesgos aceptados conscientemente

**La pantalla `/tv` es pública y muestra nombres de pacientes.**
Es una decisión de producto: el paciente tiene que reconocerse cuando lo llaman.
Está mitigado — sin cédula, sin especialidad (que dejaría inferir la condición
médica), sin datos clínicos, y con límite de 30 peticiones/minuto. Si hiciera
falta endurecerlo: mostrar iniciales, restringir por IP, o exigir un token fijo
para la TV.

**El token se guarda en `localStorage`.**
Vulnerable a XSS: un script inyectado puede leerlo. Lo robusto son cookies
`httpOnly`, que JavaScript no puede leer. Requiere rehacer el flujo de
autenticación completo.

**El nombre del paciente sigue sin cifrar.**
Es deliberado: se muestra en la TV pública de todos modos, y mantenerlo en claro
permite la búsqueda parcial por nombre, que es lo que hace útil al buscador de
Preconsulta. Cifrarlo agregaría poco y rompería esa función.

**La búsqueda por cédula requiere el número completo.**
Es el costo aceptado del cifrado: el índice ciego solo resuelve coincidencias
exactas. La cédula se normaliza (`30.125.478` y `30125478` son equivalentes), y
para búsquedas parciales está el nombre.

---

## 🔑 La APP_KEY es ahora irremplazable

Con el cifrado en reposo activo, **la `APP_KEY` es la única llave que descifra
los datos clínicos y las cédulas**. Si se pierde o se cambia:

- Todos los datos cifrados quedan **irrecuperables para siempre**.
- Los `cedula_hash` dejan de coincidir: los pacientes existentes se vuelven
  imposibles de encontrar y se duplicarían al registrarlos de nuevo.

Por lo tanto:

- [ ] Guardar la `APP_KEY` en un gestor de secretos, **fuera del servidor**.
- [ ] **Nunca** correr `php artisan key:generate` sobre una instalación con datos.
- [ ] Incluir la `APP_KEY` en el procedimiento de restauración: un backup de la
      base **sin la llave es un archivo inútil**.

---

## Código fuente

El **frontend no se puede proteger**: todo lo que llega al navegador es
inspeccionable. La minificación de Vite dificulta la lectura, pero la ofuscación
aporta poco frente al costo de debugging. La regla que ya se sigue: **ninguna
clave, lógica de negocio ni regla de autorización vive en el frontend**.

El **backend PHP nunca se envía al cliente**. Sus riesgos reales — `.env`
accesible por web, secretos en el historial de Git, listado de directorios,
versión del servidor expuesta — están todos cerrados.

Proteger el sistema frente a que alguien lo copie es un asunto de licencia y
contrato, no técnico.
