import axios from 'axios';

/**
 * Cliente HTTP del SPA.
 *
 * La sesión viaja en una cookie HttpOnly que emite el backend: no se guarda
 * ningún token en localStorage, así un XSS no puede robar la sesión.
 *
 * A cambio hace falta CSRF: como el navegador adjunta la cookie por su cuenta,
 * sin token CSRF cualquier sitio podría disparar peticiones en nombre del
 * usuario. Axios lee la cookie XSRF-TOKEN y la reenvía en X-XSRF-TOKEN.
 */
const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
});

/**
 * Pide la cookie CSRF al backend. Hay que llamarlo antes del primer POST de la
 * sesión (el login); después la cookie ya vive en el navegador.
 */
export async function obtenerCookieCsrf() {
  await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
}

export default api;
