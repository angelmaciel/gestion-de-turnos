# Frontend — Gestión de Turnos

SPA en **React 19 + Vite 8 + Tailwind CSS 4**. La documentación general y la
puesta en marcha están en el [README principal](../README.md).

```bash
npm install
npm run dev      # http://localhost:5173
npm run build
npm run lint
```

Vite hace de proxy hacia el backend: `/api` y `/sanctum` van a `http://localhost`
(nginx). Dentro de Docker hay que apuntar al servicio por su nombre con
`VITE_PROXY_TARGET=http://nginx`.

## Organización

| Ruta | Contenido |
|---|---|
| `src/pages/` | Una pantalla por rol: Login, Reception, Triage, DoctorPanel, WaitingRoom. |
| `src/components/ui/` | Primitivas de interfaz (Button, Card, Field, Input, Badge…). Viven en el repo, no en una dependencia. |
| `src/context/` | Estado de sesión. Consulta `/auth/me`; no guarda nada en el navegador. |
| `src/hooks/` | `useColaEnVivo`: recarga la pantalla cuando el backend avisa que cambió una cola. |
| `src/api/axios.jsx` | Cliente HTTP con cookies y token CSRF. |
| `src/services/echo.js` | Cliente de WebSocket (Reverb). |
| `src/index.css` | Tokens de diseño con `@theme` de Tailwind. |

## Cosas a tener en cuenta

**No se usa `localStorage` para la sesión.** La autenticación va en una cookie
`HttpOnly` que JavaScript no puede leer, y los datos del usuario se piden a
`/auth/me` en cada carga. Guardar el usuario en el navegador lo dejaría legible
por cualquier script y desactualizado respecto del backend.

**Las respuestas de la API vienen envueltas en `data`.** Son API Resources de
Laravel: una colección llega como `{ data: [...] }`, no como un array suelto.

**El `id` de un turno es un ULID**, no un número. El valor que se muestra al
paciente es `turno`, el correlativo del día.

**La pantalla `/tv` es solo de visualización**, sin controles. Lo único manual es
un clic inicial para habilitar el audio: los navegadores bloquean la reproducción
hasta que hay una interacción del usuario, y no se puede evitar por código.
