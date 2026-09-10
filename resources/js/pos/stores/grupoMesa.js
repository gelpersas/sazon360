import { defineStore } from 'pinia';
import api from '../api';

/**
 * Unión de mesas (Fase 9, DEC-025; modo General en DEC-064) — dos modos:
 * "independiente" (default, cada mesa su propio pedido — el grupo solo
 * agrupa y calcula un total de referencia) o "general" (un solo pedido
 * compartido por todo el grupo, ver App\Enums\ModoGrupoMesa). El cobro real
 * sigue pasando por `stores/pedido.js` (`pagar()`)/`stores/subCuenta.js`
 * (dividir cuenta), pedido por pedido en ambos modos — lo único que cambia
 * es cuántos pedidos hay detrás del grupo.
 */
export const useGrupoMesaStore = defineStore('grupoMesa', {
  state: () => ({
    grupos: [], // grupos activos de la sede actual (para el banner de PosView)
    grupoActivo: null, // el que se está viendo/gestionando en GrupoMesaView
    cargando: false,
    error: null,
  }),

  actions: {
    async cargarGrupos(sedeId) {
      const { data } = await api.get(`/sedes/${sedeId}/grupos-mesa`);
      this.grupos = data.data;
    },

    async cargarGrupo(grupoId) {
      const { data } = await api.get(`/grupos-mesa/${grupoId}`);
      this.grupoActivo = data.data;

      return this.grupoActivo;
    },

    // modo: 'independiente' (cada mesa su propio pedido, default) o
    // 'general' (un solo pedido compartido, siempre contra la mesa
    // principal — ver App\Enums\ModoGrupoMesa y docs/DECISIONES.md).
    // mesaPrincipalId: cuál de mesaIds debe quedar como principal — importa
    // en modo general cuando la unión arranca desde una mesa que ya tenía
    // un pedido en curso (esa debe seguir siendo la que lo recibe).
    async unirMesas(sedeId, mesaIds, modo = 'independiente', mesaPrincipalId = null) {
      const { data } = await api.post(`/sedes/${sedeId}/grupos-mesa`, {
        mesa_ids: mesaIds,
        modo,
        mesa_principal_id: mesaPrincipalId,
      });
      this.grupoActivo = data.data;
      this.grupos.push(data.data);

      return this.grupoActivo;
    },

    // Suma UNA mesa a un grupo que ya existe — a diferencia de unirMesas()
    // (que crea un grupo nuevo desde cero, mínimo 2 mesas), esto es para
    // cuando el grupo de comensales crece después de haber empezado a
    // atender (ver docs/DECISIONES.md).
    async agregarMesa(grupoId, mesaId) {
      const { data } = await api.post(`/grupos-mesa/${grupoId}/mesas`, { mesa_id: mesaId });
      this.grupoActivo = data.data;

      const indice = this.grupos.findIndex((g) => g.id === grupoId);
      if (indice !== -1) this.grupos[indice] = data.data;

      return data.data;
    },

    async disolver(grupoId) {
      const { data } = await api.post(`/grupos-mesa/${grupoId}/disolver`);
      this.grupos = this.grupos.filter((g) => g.id !== grupoId);

      if (this.grupoActivo?.id === grupoId) this.grupoActivo = null;

      return data.data;
    },
  },
});
