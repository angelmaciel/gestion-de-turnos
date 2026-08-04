import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

/**
 * Reverb en local (docker-compose, sin cuenta externa) y Pusher en el deploy
 * público (sin proceso de socket propio que mantener corriendo). Los dos
 * hablan el mismo protocolo, así que el cliente (`pusher-js`) es el mismo;
 * solo cambia a qué servidor se conecta.
 */
const opciones = import.meta.env.VITE_BROADCASTER === 'pusher'
  ? {
      broadcaster: 'pusher',
      key: import.meta.env.VITE_PUSHER_APP_KEY,
      cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
      forceTLS: true,
    }
  : {
      broadcaster: 'reverb',
      key: import.meta.env.VITE_REVERB_APP_KEY,
      wsHost: import.meta.env.VITE_REVERB_HOST || '127.0.0.1',
      wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
      wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
      forceTLS: false,
      enabledTransports: ['ws', 'wss'],
    };

export const echo = new Echo(opciones);