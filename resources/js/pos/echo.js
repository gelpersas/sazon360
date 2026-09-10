import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import axios from 'axios';

window.Pusher = Pusher;

let echo = null;

/**
 * Reverb (Fase 8, ver docs/DECISIONES.md) — protocolo compatible con Pusher.
 * La autorización de canales privados usa la ruta estándar de Laravel
 * (`/broadcasting/auth`, sesión `web` — el mismo login del POS, ver
 * AuthController), no las rutas `/api/pos/*`: por eso el authorizer usa
 * `axios` directo (con la cookie de sesión y el token CSRF ya presentes
 * desde el login) en vez de la instancia `api` (baseURL `/api/pos`).
 */
export function obtenerEcho() {
  if (echo) return echo;

  echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel) => ({
      authorize: (socketId, callback) => {
        axios
          .post(
            '/broadcasting/auth',
            { socket_id: socketId, channel_name: channel.name },
            { withCredentials: true, withXSRFToken: true },
          )
          .then((response) => callback(false, response.data))
          .catch((error) => callback(true, error));
      },
    }),
  });

  return echo;
}

export function desconectarEcho() {
  echo?.disconnect();
  echo = null;
}
