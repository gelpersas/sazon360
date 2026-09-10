import { defineStore } from 'pinia';
import api from '../api';

export const useDashboardStore = defineStore('dashboard', {
  state: () => ({
    rango: 'hoy', // 'hoy' | 'semana' | 'mes' — solo en memoria, se pierde al recargar (a propósito, no hace falta persistirlo)
    datos: null,
    cargando: false,
  }),

  actions: {
    async cargar(sedeId) {
      this.cargando = true;

      try {
        const { data } = await api.get(`/sedes/${sedeId}/dashboard`, {
          params: { rango: this.rango },
        });
        this.datos = data.data;
      } finally {
        this.cargando = false;
      }
    },

    async cambiarRango(sedeId, rango) {
      this.rango = rango;
      await this.cargar(sedeId);
    },
  },
});
