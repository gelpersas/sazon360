<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { useAuthStore } from '../stores/auth';
import api, { asegurarCsrf } from '../api';
import { obtenerEcho } from '../echo';

const auth = useAuthStore();
const comandas = ref([]);
const error = ref(null);

// Tiempo real vía WebSockets (Reverb) — reemplaza el polling del MVP (ver
// docs/DECISIONES.md DEC-002 y Fase 8). 'lista' se incluye porque el propio
// tablero necesita seguir mostrando una comanda lista para poder
// entregarla — antes quedaba fuera del filtro y el botón "Entregar" era
// inalcanzable en la práctica (ver ComandaController::index()).
const ESTADOS_VISIBLES = ['pendiente', 'en_preparacion', 'lista'];
let nombreCanal = null;

async function cargar() {
  try {
    const { data } = await api.get(`/sedes/${auth.sedeActualId}/comandas`);
    comandas.value = data.data;
    error.value = null;
  } catch {
    error.value = 'No se pudo cargar el tablero.';
  }
}

function actualizarComanda(comanda) {
  const indice = comandas.value.findIndex((c) => c.id === comanda.id);

  if (!ESTADOS_VISIBLES.includes(comanda.estado)) {
    if (indice !== -1) comandas.value.splice(indice, 1);
    return;
  }

  if (indice === -1) {
    comandas.value.push(comanda);
  } else {
    comandas.value[indice] = comanda;
  }
}

async function avanzar(comandaId) {
  try {
    await api.post(`/comandas/${comandaId}/avanzar`);
    // El propio evento de WebSocket actualiza el tablero — no hace falta
    // recargar manualmente.
  } catch {
    error.value = 'No se pudo avanzar esa comanda.';
  }
}

const etiquetaSiguiente = {
  pendiente: 'Empezar',
  en_preparacion: 'Marcar lista',
  lista: 'Entregar',
};

onMounted(async () => {
  await asegurarCsrf();
  await cargar();

  nombreCanal = `sede.${auth.sedeActualId}.comandas`;

  obtenerEcho()
    .private(nombreCanal)
    .listen('.comanda.actualizada', actualizarComanda)
    .error(() => {
      error.value = 'Conexión en tiempo real perdida — reintentando…';
    });
});

onUnmounted(() => {
  if (nombreCanal) obtenerEcho().leave(nombreCanal);
});
</script>

<template>
  <div class="min-h-screen bg-bg p-4 text-text">
    <p v-if="error" class="mb-4 rounded-lg bg-warning-soft px-3 py-2 text-sm text-warning">{{ error }}</p>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="comanda in comandas"
        :key="comanda.id"
        class="rounded-2xl bg-surface p-4"
        :class="{ 'ring-2 ring-warning': comanda.estado === 'en_preparacion' }"
      >
        <p class="mb-1 text-sm text-text-muted">{{ comanda.area }}</p>
        <p class="mb-2 font-semibold text-text">
          {{ comanda.mesa ?? comanda.tipo_pedido }} · #{{ comanda.pedido_id }}
        </p>

        <ul class="mb-3 space-y-1">
          <li v-for="(item, i) in comanda.items" :key="i" class="text-sm text-text">
            {{ item.cantidad }}× {{ item.nombre_producto }}
            <span v-if="item.notas" class="text-text-muted">({{ item.notas }})</span>
          </li>
        </ul>

        <button
          class="w-full rounded-xl bg-success py-3 font-medium text-white"
          @click="avanzar(comanda.id)"
        >
          {{ etiquetaSiguiente[comanda.estado] ?? 'Avanzar' }}
        </button>
      </div>

      <p v-if="comandas.length === 0" class="text-text-muted">No hay comandas pendientes.</p>
    </div>
  </div>
</template>
