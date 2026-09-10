<script setup>
import { computed, reactive, ref, watch } from 'vue';
import PagoModal from './PagoModal.vue';

// onCrear/onEliminar/onPagar se pasan como props (funciones que devuelven
// una promesa), no como eventos — `emit()` en Vue 3 no espera a que el
// handler del padre termine (siempre "resuelve" de inmediato), así que un
// `await emit(...)` no detecta errores reales del backend ni evita limpiar
// el formulario antes de tiempo. Con una función como prop sí se puede
// esperar de verdad el resultado real.
const props = defineProps({
  pedido: { type: Object, required: true },
  subCuentas: { type: Array, required: true },
  // El backend igual rechaza el pago si el rol no puede cobrar (ver
  // App\Enums\Rol::accedeACaja()) — esto solo evita mostrar el botón a quien
  // de todos modos no podría usarlo (ej. un mesero dividiendo la cuenta).
  puedeCobrar: { type: Boolean, default: true },
  onCrear: { type: Function, required: true },
  onEliminar: { type: Function, required: true },
  onPagar: { type: Function, required: true },
});

const emit = defineEmits(['cerrar']);

const nombreNueva = ref('');
const seleccion = reactive({}); // { [itemId]: { incluido: bool, porcentaje: number } }
const errorLocal = ref(null);
const enviando = ref(false);
const subCuentaParaPagar = ref(null);

// Porcentaje ya asignado de cada ítem entre TODAS las sub-cuentas existentes
// del pedido — para no dejar asignar más del 100% de un mismo ítem.
const porcentajeAsignado = computed(() => {
  const mapa = {};

  for (const sub of props.subCuentas) {
    for (const subItem of sub.items) {
      mapa[subItem.item_pedido_id] = (mapa[subItem.item_pedido_id] ?? 0) + Number(subItem.porcentaje);
    }
  }

  return mapa;
});

function disponible(itemId) {
  return Math.max(0, 100 - (porcentajeAsignado.value[itemId] ?? 0));
}

watch(
  () => props.pedido.items,
  (items) => {
    for (const item of items) {
      if (!seleccion[item.id]) {
        seleccion[item.id] = { incluido: false, porcentaje: disponible(item.id) };
      }
    }
  },
  { immediate: true },
);

function alternarItem(item) {
  const estado = seleccion[item.id];
  estado.incluido = !estado.incluido;
  if (estado.incluido) estado.porcentaje = disponible(item.id);
}

async function confirmarCrear() {
  if (enviando.value) return; // prevención de doble toque
  errorLocal.value = null;

  if (!nombreNueva.value.trim()) {
    errorLocal.value = 'Ponle un nombre a la sub-cuenta.';
    return;
  }

  const asignaciones = props.pedido.items
    .filter((item) => seleccion[item.id]?.incluido)
    .map((item) => ({ item_pedido_id: item.id, porcentaje: Number(seleccion[item.id].porcentaje) }));

  if (asignaciones.length === 0) {
    errorLocal.value = 'Elige al menos un ítem.';
    return;
  }

  enviando.value = true;

  try {
    await props.onCrear({ nombre: nombreNueva.value.trim(), asignaciones });
    nombreNueva.value = '';
    for (const item of props.pedido.items) {
      seleccion[item.id] = { incluido: false, porcentaje: disponible(item.id) };
    }
  } catch (e) {
    errorLocal.value = e.response?.data?.message ?? 'No se pudo crear la sub-cuenta.';
  } finally {
    enviando.value = false;
  }
}

async function eliminar(sub) {
  try {
    await props.onEliminar(sub);
  } catch (e) {
    errorLocal.value = e.response?.data?.message ?? 'No se pudo eliminar esa sub-cuenta.';
  }
}

function abrirPago(sub) {
  subCuentaParaPagar.value = sub;
}

// Ítems a mostrar en la columna izquierda de PagoModal (ver
// docs/DECISIONES.md DEC-030) — solo informativo, incluye el % asignado
// porque un mismo ítem puede repartirse entre varias sub-cuentas.
const itemsParaCobro = computed(
  () => (subCuentaParaPagar.value?.items ?? []).map((i) => ({
    label: `${i.nombre_producto} (${i.porcentaje}%)`,
    monto: i.monto,
  })),
);

async function confirmarPago(payload) {
  try {
    await props.onPagar({ subCuenta: subCuentaParaPagar.value, ...payload });
    subCuentaParaPagar.value = null;
  } catch (e) {
    errorLocal.value = e.response?.data?.message ?? 'No se pudo registrar el pago.';
  }
}
</script>

<template>
  <div class="fixed inset-0 z-20 flex items-center justify-center bg-black/40">
    <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-surface p-6">
      <h2 class="mb-4 text-lg font-semibold text-text">Dividir cuenta</h2>

      <div v-if="subCuentas.length > 0" class="mb-6 space-y-2">
        <div v-for="sub in subCuentas" :key="sub.id" class="rounded-xl border border-border p-3">
          <div class="flex items-center justify-between">
            <p class="font-medium text-text">{{ sub.nombre }}</p>
            <p class="text-text">${{ sub.total }}</p>
          </div>
          <p v-if="sub.saldo_pendiente !== '0.00'" class="text-sm text-warning">
            Pendiente: ${{ sub.saldo_pendiente }}
          </p>
          <p v-else class="text-sm text-success">Pagada</p>
          <div class="mt-2 flex gap-2">
            <button
              v-if="sub.saldo_pendiente !== '0.00' && puedeCobrar"
              class="rounded-lg bg-success px-3 py-2 text-sm font-medium text-white"
              @click="abrirPago(sub)"
            >
              Cobrar
            </button>
            <button
              v-if="sub.total_pagado === '0.00'"
              class="rounded-lg border border-danger px-3 py-2 text-sm text-danger"
              @click="eliminar(sub)"
            >
              Eliminar
            </button>
          </div>
        </div>
      </div>

      <h3 class="mb-2 text-sm font-semibold text-text-muted">Nueva sub-cuenta</h3>
      <input
        v-model="nombreNueva"
        type="text"
        placeholder="Nombre (ej. Juan)"
        class="mb-3 w-full rounded-xl border border-border bg-surface px-4 py-3 text-text"
      >

      <div class="mb-3 space-y-2">
        <div v-for="item in pedido.items" :key="item.id" class="rounded-xl border border-border p-3">
          <label class="flex items-center justify-between gap-2">
            <span class="flex items-center gap-2 text-text">
              <input
                type="checkbox"
                :checked="seleccion[item.id]?.incluido"
                :disabled="disponible(item.id) === 0 && !seleccion[item.id]?.incluido"
                @change="alternarItem(item)"
              >
              {{ item.cantidad }}× {{ item.nombre_producto }}
            </span>
            <span class="shrink-0 text-xs text-text-muted">disponible: {{ disponible(item.id) }}%</span>
          </label>
          <div v-if="seleccion[item.id]?.incluido" class="mt-2 flex items-center gap-2">
            <input
              v-model.number="seleccion[item.id].porcentaje"
              type="number"
              min="1"
              :max="disponible(item.id)"
              class="w-20 rounded-lg border border-border bg-surface px-2 py-1 text-sm text-text"
            >
            <span class="text-sm text-text-muted">%</span>
          </div>
        </div>
        <p v-if="pedido.items.length === 0" class="text-sm text-text-muted">Este pedido todavía no tiene ítems.</p>
      </div>

      <p v-if="errorLocal" class="mb-3 text-sm text-danger" role="alert">{{ errorLocal }}</p>

      <div class="flex gap-2">
        <button
          class="flex-1 rounded-xl bg-success py-3 font-medium text-white disabled:opacity-50"
          :disabled="enviando"
          @click="confirmarCrear"
        >
          {{ enviando ? 'Creando…' : 'Crear sub-cuenta' }}
        </button>
        <button class="rounded-xl border border-border px-4 py-3 text-text-muted" @click="emit('cerrar')">
          Cerrar
        </button>
      </div>
    </div>

    <PagoModal
      v-if="subCuentaParaPagar"
      :saldo-pendiente="subCuentaParaPagar.saldo_pendiente"
      :items="itemsParaCobro"
      @cerrar="subCuentaParaPagar = null"
      @confirmar="confirmarPago"
    />
  </div>
</template>
