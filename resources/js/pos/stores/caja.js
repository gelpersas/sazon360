import { defineStore } from 'pinia';
import api from '../api';

export const useCajaStore = defineStore('caja', {
  state: () => ({
    actual: null, // null = no hay caja abierta en la sede
  }),

  actions: {
    async cargar(sedeId) {
      const { data } = await api.get(`/sedes/${sedeId}/caja`);
      this.actual = data.data;
    },

    async abrir(sedeId, { montoInicial, nota = null }) {
      const { data } = await api.post(`/sedes/${sedeId}/caja/abrir`, {
        monto_inicial: montoInicial,
        nota,
      });
      this.actual = data.data;
    },

    async registrarMovimiento({ tipo, monto, descripcion }) {
      const { data } = await api.post(`/cajas/${this.actual.id}/movimientos`, {
        tipo,
        monto,
        descripcion,
      });
      this.actual = data.data;
    },

    async cerrar({ montoReal, nota = null }) {
      const { data } = await api.post(`/cajas/${this.actual.id}/cerrar`, {
        monto_real: montoReal,
        nota,
      });

      this.actual = null;

      return data.data;
    },
  },
});
