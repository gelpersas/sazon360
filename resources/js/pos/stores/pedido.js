import { defineStore } from 'pinia';
import api, { asegurarCsrf } from '../api';
import { generarIdempotencyKey } from '../idempotency';

export const usePedidoStore = defineStore('pedido', {
  state: () => ({
    mesas: [],
    areas: [],
    categorias: [],
    pedidosAbiertos: [],
    pedidoActivo: null,
    cargando: false,
    error: null,
  }),

  actions: {
    async cargarReferencia(sedeId) {
      await asegurarCsrf();

      const [mesas, areas, catalogo] = await Promise.all([
        api.get(`/sedes/${sedeId}/mesas`),
        api.get(`/sedes/${sedeId}/areas`),
        api.get(`/sedes/${sedeId}/catalogo`),
      ]);

      this.mesas = mesas.data.data;
      this.areas = areas.data.data;
      this.categorias = catalogo.data.data;
    },

    async cargarPedidosAbiertos(sedeId) {
      const { data } = await api.get(`/sedes/${sedeId}/pedidos`);
      this.pedidosAbiertos = data.data;
    },

    async abrirPedido(sedeId, { tipo, mesaId = null, notas = null }) {
      const { data } = await this.conReintento(() =>
        api.post(`/sedes/${sedeId}/pedidos`, {
          tipo,
          mesa_id: mesaId,
          notas,
          idempotency_key: generarIdempotencyKey(),
        }),
      );

      this.pedidoActivo = data.data;

      return this.pedidoActivo;
    },

    async seleccionarPedido(pedidoId) {
      const { data } = await api.get(`/pedidos/${pedidoId}`);
      this.pedidoActivo = data.data;
    },

    // Igual que seleccionarPedido() pero SIN tocar pedidoActivo — para
    // previsualizar un pedido de la lista (ver items/total) sin entrar de
    // verdad al carrito/catálogo de esa mesa.
    async obtenerPedido(pedidoId) {
      const { data } = await api.get(`/pedidos/${pedidoId}`);
      return data.data;
    },

    cerrarPedidoActivo() {
      this.pedidoActivo = null;
    },

    async agregarItem({ productoId, areaPreparacionId, cantidad = 1, notas = null }) {
      const { data } = await api.post(`/pedidos/${this.pedidoActivo.id}/items`, {
        producto_id: productoId,
        area_preparacion_id: areaPreparacionId,
        cantidad,
        notas,
      });

      this.pedidoActivo = data.data;
    },

    async quitarItem(itemId) {
      const { data } = await api.delete(`/pedidos/${this.pedidoActivo.id}/items/${itemId}`);
      this.pedidoActivo = data.data;
    },

    async actualizarCantidadItem(itemId, cantidad) {
      const { data } = await api.patch(`/pedidos/${this.pedidoActivo.id}/items/${itemId}`, { cantidad });
      this.pedidoActivo = data.data;
    },

    async actualizarNotasItem(itemId, notas) {
      const { data } = await api.patch(`/pedidos/${this.pedidoActivo.id}/items/${itemId}`, { notas });
      this.pedidoActivo = data.data;
    },

    async enviarComanda() {
      const { data } = await this.conReintento(() =>
        api.post(`/pedidos/${this.pedidoActivo.id}/enviar-comanda`),
      );

      this.pedidoActivo = data.data;
    },

    async pagar({ medio, monto }) {
      const { data } = await this.conReintento(() =>
        api.post(`/pedidos/${this.pedidoActivo.id}/pagos`, {
          medio,
          monto,
          idempotency_key: generarIdempotencyKey(),
        }),
      );

      this.pedidoActivo = data.data;
    },

    async anular() {
      const { data } = await api.post(`/pedidos/${this.pedidoActivo.id}/anular`);
      this.pedidoActivo = data.data;
    },

    // Cliente al que se factura el pedido (opcional, al cobrar — ver
    // docs/DECISIONES.md). Sin asignar, la factura sale a "consumidor
    // final". buscarClientes/crearCliente no tocan pedidoActivo: son
    // consultas de referencia para el selector, no del pedido en sí.
    async buscarClientes(sedeId, termino) {
      const { data } = await api.get(`/sedes/${sedeId}/clientes`, { params: { buscar: termino } });
      return data.data;
    },

    async crearCliente(sedeId, datos) {
      const { data } = await api.post(`/sedes/${sedeId}/clientes`, datos);
      return data.data;
    },

    async asignarCliente(clienteId) {
      const { data } = await api.patch(`/pedidos/${this.pedidoActivo.id}/cliente`, { cliente_id: clienteId });
      this.pedidoActivo = data.data;
    },

    /**
     * Un solo reintento automático ante un corte de red (no ante un error
     * de validación/negocio, que no se arregla reintentando). La clave de
     * idempotencia ya generada por el caller hace que el reintento sea
     * seguro — ver docs/DECISIONES.md DEC-009 y resources/js/pos/idempotency.js.
     */
    async conReintento(peticion) {
      try {
        return await peticion();
      } catch (e) {
        if (e.response) throw e; // el servidor respondió (4xx/5xx): no es un corte de red

        return await peticion();
      }
    },
  },
});
