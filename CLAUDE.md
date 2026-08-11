# Sistema de Gestión de Turnos

Gestión de turnos para un centro de salud: registro de pacientes, preconsulta,
llamado desde el consultorio y anuncio en la pantalla de la sala de espera, con
actualización en tiempo real (WebSocket) y aviso por voz.

Ver [README.md](README.md) para el flujo completo y las capturas, y
[SEGURIDAD.md](SEGURIDAD.md) para el modelo de autenticación y permisos.

## Estructura

| Carpeta | Contenido |
| --- | --- |
| `gestion-turnos-back/` | API Laravel 13 (PHP 8.3), Reverb para WebSocket, Sanctum, spatie/laravel-permission |
| `gestion-turnos-front/` | SPA React 19 con Vite, React Router, Tailwind 4, laravel-echo + pusher-js |
| `docker/`, `Dockerfile`, `render.yaml` | Empaquetado y despliegue en Render |
| `docs/` | Capturas y documentación |
| `_backup-local/` | Respaldo antiguo. **No editar ni tomar como referencia.** |

## Comandos

Backend (desde `gestion-turnos-back/`):

```bash
composer setup     # install + .env + key:generate + migrate + build
composer dev       # serve + queue:listen + pail + vite, todo junto
composer test      # config:clear + artisan test
```

Frontend (desde `gestion-turnos-front/`):

```bash
npm run dev
npm run build
npm run lint
```

## Convenciones

- La sesión va por cookie HttpOnly; no se guardan tokens en el cliente.
- Los permisos se manejan con spatie/laravel-permission, no con chequeos de rol
  a mano en los controladores.
- Los eventos en vivo (llamado de turno, estado de salas) van por Reverb; el
  frontend escucha con laravel-echo, no hace polling.
- Análisis estático con Larastan (`phpstan.neon`) y tests con PHPUnit
  (`phpunit.xml`) — correrlos antes de dar por cerrado un cambio en el backend.
