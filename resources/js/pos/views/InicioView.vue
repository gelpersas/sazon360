<script setup>
import { computed, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import { useDashboardStore } from '../stores/dashboard';

// Página de inicio del POS (ver docs/DECISIONES.md DEC-065) — mismos datos
// para cualquier rol de la sede, solo cambia qué tarjeta se destaca primero
// (pastelero → pedidos activos, mesero → mesas ocupadas) via `order-*`, no
// contenido oculto: todos los roles ven las mismas 3 métricas + inventario.
const auth = useAuthStore();
const dashboard = useDashboardStore();

const esPastelero = computed(() => auth.rolesActuales.includes('area_preparacion'));
const esMesero = computed(() => auth.rolesActuales.includes('mesero'));

const RANGOS = [
  { valor: 'hoy', etiqueta: 'Hoy' },
  { valor: 'semana', etiqueta: 'Semana' },
  { valor: 'mes', etiqueta: 'Mes' },
];

onMounted(() => {
  dashboard.cargar(auth.sedeActualId);
});
</script>

<template>
  <div class="min-h-screen bg-bg p-4 pb-8">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-xl font-semibold text-text">Inicio · {{ auth.sedeActual?.nombre }}</h1>

      <div class="flex gap-1 rounded-xl bg-surface-soft p-1">
        <button
          v-for="r in RANGOS"
          :key="r.valor"
          class="rounded-lg px-4 py-2 text-sm font-medium"
          :class="dashboard.rango === r.valor ? 'bg-primary text-white' : 'text-text-muted'"
          @click="dashboard.cambiarRango(auth.sedeActualId, r.valor)"
        >
          {{ r.etiqueta }}
        </button>
      </div>
    </div>

    <p v-if="dashboard.cargando && !dashboard.datos" class="text-center text-text-muted">Cargando…</p>

    <div v-else-if="dashboard.datos" class="mx-auto max-w-3xl space-y-4">
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-2xl bg-surface p-5 shadow">
          <p class="text-sm text-text-muted">Ventas</p>
          <p class="text-2xl font-semibold text-text">${{ dashboard.datos.ventas_total }}</p>
        </div>

        <div class="rounded-2xl bg-surface p-5 shadow" :class="esPastelero ? 'order-first ring-2 ring-primary sm:order-none' : ''">
          <p class="text-sm text-text-muted">Pedidos activos / en cocina</p>
          <p class="text-2xl font-semibold text-text">{{ dashboard.datos.pedidos_activos }}</p>
        </div>

        <div class="rounded-2xl bg-surface p-5 shadow" :class="esMesero ? 'order-first ring-2 ring-primary sm:order-none' : ''">
          <p class="text-sm text-text-muted">Mesas ocupadas</p>
          <p class="text-2xl font-semibold text-text">{{ dashboard.datos.mesas_ocupadas }}</p>
        </div>
      </div>

      <div class="rounded-2xl bg-surface p-5 shadow">
        <h2 class="mb-3 text-sm font-semibold text-text-muted">Inventario bajo</h2>

        <ul v-if="dashboard.datos.inventario_bajo.length > 0" class="divide-y divide-border">
          <li v-for="i in dashboard.datos.inventario_bajo" :key="i.insumo" class="flex items-center justify-between py-2">
            <span class="text-text">{{ i.insumo }}</span>
            <span class="text-sm text-warning">{{ i.cantidad_actual }} / mín. {{ i.stock_minimo }}</span>
          </li>
        </ul>
        <p v-else class="text-sm text-text-muted">Sin alertas de inventario en esta sede.</p>
      </div>
    </div>
  </div>
</template>
