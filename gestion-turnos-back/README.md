# Backend — Gestión de Turnos

API REST en **Laravel 13 + PHP 8.4**. La documentación general del sistema y la
puesta en marcha están en el [README principal](../README.md).

## Organización

| Carpeta | Contenido |
|---|---|
| `app/Models` | Modelos del dominio. `Patient` y `Appointment` cifran sus datos sensibles. |
| `app/Enums` | `AppointmentStatus`: los cinco estados del turno. |
| `app/Http/Controllers/Api` | Controladores. Nunca devuelven modelos Eloquent. |
| `app/Http/Requests` | Validación y autorización de entrada. |
| `app/Http/Resources` | Forma de las respuestas. Recortan campos según el rol. |
| `app/Policies` | Quién puede operar cada turno. Filtran por pertenencia. |
| `app/Services` | Lógica de varios pasos y con concurrencia. |
| `app/Events` | `PacienteLlamado` (pantalla de sala) y `ColaActualizada` (paneles). |
| `database/migrations` | Esquema. Todas reversibles. |
| `database/seeders` | Roles, especialidades, salas, profesionales y datos de ejemplo. |
| `routes/api.php` | Endpoints agrupados por rol. |

## Endpoints

```
POST   /api/auth/login                            público (5 intentos/min)
POST   /api/auth/logout                           autenticado
GET    /api/auth/me                               autenticado

GET    /api/publico/llamados                      público (30 req/min)

GET    /api/especialidades                        autenticado
POST   /api/especialidades                        admin

POST   /api/entrada/turnos                        mesa de entrada | admin

GET    /api/preconsulta/turnos                    preconsulta | admin
POST   /api/preconsulta/turnos/{ulid}/complete    preconsulta | admin

GET    /api/profesional/cola                      profesional
POST   /api/profesional/turnos/{ulid}/call        profesional
POST   /api/profesional/turnos/{ulid}/attend      profesional
POST   /api/profesional/turnos/{ulid}/absent      profesional
```

Las rutas resuelven por **ULID**, nunca por el id autoincremental.

## Servicios de Docker

| Servicio | Rol |
|---|---|
| `app` | PHP-FPM 8.4, como usuario `www-data` |
| `nginx` | Servidor web, único puerto publicado (80) |
| `mysql` | Base de datos, accesible solo desde `127.0.0.1` |
| `redis` | Caché, colas y locks. Con contraseña. |
| `reverb` | Servidor WebSocket (8080) |

`vendor/` vive en un volumen de Docker y no en el bind mount: en Windows la
diferencia de rendimiento es enorme por la cantidad de archivos que Laravel lee
en cada petición.

Como el contenedor corre sin privilegios, no puede escribir `composer.lock`.
Para cambiar dependencias:

```bash
docker compose exec -u root app composer require ...
docker compose exec -u root app chown -R www-data:www-data /var/www/html/vendor
```

## Comandos

```bash
docker compose exec app php artisan migrate                # aplica lo que falte
docker compose exec app php artisan migrate:fresh --seed   # DESTRUCTIVO
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint
docker compose exec app ./vendor/bin/phpstan analyse
```

`migrate:fresh` borra todo, incluidos los tokens de sesión: después hay que
volver a iniciar sesión en el navegador.
