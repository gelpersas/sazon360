import { defineStore } from 'pinia';
import api, { asegurarCsrf } from '../api';
import { useTemaStore } from './tema';

const CLAVE_SEDE = 'sazon360.pos.sede_id';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    usuario: null,
    sedes: [],
    sedeActualId: Number(localStorage.getItem(CLAVE_SEDE)) || null,
    cargando: false,
    error: null,
  }),

  getters: {
    autenticado: (estado) => estado.usuario !== null,
    sedeActual: (estado) => estado.sedes.find((s) => s.id === estado.sedeActualId) ?? null,

    // Segregación por rol dentro del POS: cada operativo ve/usa solo su
    // propia área, administración (central o de sede) siempre tiene acceso
    // a todo — el backend es quien realmente autoriza (App\Enums\Rol), esto
    // solo evita mostrar botones/pantallas que igual serían rechazados. Un
    // usuario puede tener MÁS DE UN rol en la misma sede si el
    // administrador se lo asigna (ver User::rolesEnSede() en el backend) —
    // por eso "roles" es un arreglo, no un solo valor, y cada puedeVerX
    // pregunta si ALGUNO de los roles lo permite.
    rolesActuales() {
      return this.sedeActual?.roles ?? [];
    },
    puedeVerMostrador() {
      return this.rolesActuales.some((rol) => ['mesero', 'caja', 'administracion_sede', 'administracion_central'].includes(rol));
    },
    puedeVerCocina() {
      return this.rolesActuales.some((rol) => ['area_preparacion', 'administracion_sede', 'administracion_central'].includes(rol));
    },
    puedeVerCaja() {
      return this.rolesActuales.some((rol) => ['caja', 'administracion_sede', 'administracion_central'].includes(rol));
    },
    rutaInicioSegunRol() {
      if (this.puedeVerMostrador) return { name: 'pos' };
      if (this.puedeVerCocina) return { name: 'kds' };
      if (this.puedeVerCaja) return { name: 'caja' };
      return { name: 'login' };
    },
  },

  actions: {
    async iniciarSesion(email, password) {
      this.cargando = true;
      this.error = null;

      try {
        await asegurarCsrf();
        const { data } = await api.post('/login', { email, password });
        this.establecerUsuario(data);
      } catch (e) {
        this.error = e.response?.data?.message ?? 'No se pudo iniciar sesión.';
        throw e;
      } finally {
        this.cargando = false;
      }
    },

    async cerrarSesion() {
      try {
        await api.post('/logout');
      } finally {
        this.usuario = null;
        this.sedes = [];
        this.sedeActualId = null;
        localStorage.removeItem(CLAVE_SEDE);
      }
    },

    async recuperarSesion() {
      try {
        const { data } = await api.get('/me');
        this.establecerUsuario(data);
      } catch {
        this.usuario = null;
      }
    },

    establecerUsuario(data) {
      this.usuario = { id: data.id, name: data.name, email: data.email };
      this.sedes = data.sedes;

      if (!this.sedeActualId && this.sedes.length === 1) {
        this.elegirSede(this.sedes[0].id);
      }

      // La base de datos es la fuente de verdad del tema (sigue al usuario
      // entre dispositivos) — ver stores/tema.js.
      useTemaStore().sincronizarDesdeServidor(data.tema);
    },

    elegirSede(sedeId) {
      this.sedeActualId = sedeId;
      localStorage.setItem(CLAVE_SEDE, String(sedeId));
    },
  },
});
