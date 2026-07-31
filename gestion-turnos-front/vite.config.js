import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

// En el host, nginx del backend responde en http://localhost.
// Dentro de Docker hay que apuntar al servicio por nombre (http://nginx),
// porque ahí 'localhost' sería el propio contenedor del frontend.
const proxyTarget = process.env.VITE_PROXY_TARGET || 'http://localhost';

export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    host: true,
    proxy: {
      '/api': {
        target: proxyTarget,
        changeOrigin: true,
        secure: false,
      },
      // La cookie CSRF de Sanctum se pide fuera de /api.
      // changeOrigin: false para que el backend vea el Host original y
      // reconozca la petición como "stateful" (SANCTUM_STATEFUL_DOMAINS).
      '/sanctum': {
        target: proxyTarget,
        changeOrigin: false,
        secure: false,
      },
    },
  },
});
