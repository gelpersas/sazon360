import { defineStore } from 'pinia';
import api from '../api';
import { generarIdempotencyKey } from '../idempotency';

/**
 * "Por productos"/"por personas" (Fase 10, ver docs/DECISIONES.md DEC-026)
 * — una sub-cuenta reparte ítems de un pedido; cobrarla registra un `Pago`
 * normal (vía stores/pedido.js internamente en el backend), solo
 * etiquetado. No fusiona ni reemplaza el pedido.
 */
export const useSubCuentaStore = defineStore('subCuenta', {
  state: () => ({
    lista: [],
    cargando: false,
    error: null,
  }),

  actions: {
    async cargar(pedidoId) {
      const { data } = await api.get(`/pedidos/${pedidoId}/sub-cuentas`);
      this.lista = data.data;
    },

    async crear(pedidoId, nombre, asignaciones) {
      const { data } = await api.post(`/pedidos/${pedidoId}/sub-cuentas`, { nombre, asignaciones });
      this.lista.push(data.data);

      return data.data;
    },

    async eliminar(subCuentaId) {
      await api.delete(`/sub-cuentas/${subCuentaId}`);
      this.lista = this.lista.filter((s) => s.id !== subCuentaId);
    },

    async pagar(subCuentaId, { medio, monto }) {
      const { data } = await api.post(`/sub-cuentas/${subCuentaId}/pagos`, {
        medio,
        monto,
        idempotency_key: generarIdempotencyKey(),
      });

      const indice = this.lista.findIndex((s) => s.id === subCuentaId);
      if (indice !== -1) this.lista[indice] = data.data;

      return data.data;
    },

    limpiar() {
      this.lista = [];
    },
  },
});
