import axios from 'axios';

const api = axios.create({
  baseURL: '/api/pos',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
  },
});

let csrfListo = false;

/**
 * Sanctum (SPA) necesita la cookie XSRF-TOKEN antes de cualquier request que
 * cambie estado. Se pide una sola vez por sesión de la pestaña.
 */
export async function asegurarCsrf() {
  if (csrfListo) return;
  await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
  csrfListo = true;
}

export default api;
