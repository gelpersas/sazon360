import { defineStore } from 'pinia';
import api from '../api';

const CLAVE_TEMA = 'sazon360.pos.tema';

function prefiereOscuroDelSistema() {
  return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

/**
 * Preferencia de tema (claro/oscuro/auto) — la misma columna `users.tema`
 * que usa Filament (ver App\Models\User::actualizarTema()), así que sigue al
 * usuario entre dispositivos y no solo vive en el localStorage de este
 * navegador. localStorage sigue siendo el valor inicial (evita el parpadeo
 * de "tema equivocado" antes de que responda /me), pero en cuanto llega la
 * respuesta del servidor, la base de datos gana (ver
 * sincronizarDesdeServidor()).
 */
export const useTemaStore = defineStore('tema', {
  state: () => ({
    preferencia: localStorage.getItem(CLAVE_TEMA) || 'auto',
  }),

  getters: {
    oscuroActivo: (estado) => estado.preferencia === 'oscuro' || (estado.preferencia === 'auto' && prefiereOscuroDelSistema()),
  },

  actions: {
    aplicar() {
      document.documentElement.classList.toggle('dark', this.oscuroActivo);
    },

    // Se llama una sola vez al arrancar la app (main.js), antes de montar,
    // para que la primera pintura ya use el tema correcto (sin parpadeo).
    inicializar() {
      this.aplicar();

      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (this.preferencia === 'auto') this.aplicar();
      });
    },

    async establecer(preferencia) {
      this.preferencia = preferencia;
      localStorage.setItem(CLAVE_TEMA, preferencia);
      this.aplicar();

      try {
        await api.patch('/me/tema', { tema: preferencia });
      } catch {
        // Best-effort: el tema ya se ve bien en este navegador (localStorage
        // ya lo tiene) — solo no viajará a otro dispositivo por ahora.
      }
    },

    // Llamado tras login/recuperarSesion (ver stores/auth.js) con el valor
    // que devuelve el backend — si otro dispositivo cambió la preferencia,
    // la base de datos gana sobre lo que hubiera en este localStorage.
    sincronizarDesdeServidor(temaGuardado) {
      if (temaGuardado && temaGuardado !== this.preferencia) {
        this.preferencia = temaGuardado;
        localStorage.setItem(CLAVE_TEMA, temaGuardado);
        this.aplicar();
      }
    },
  },
});
