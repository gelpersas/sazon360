<script setup>
import { computed, ref } from 'vue';
import ClienteSelector from './ClienteSelector.vue';

const props = defineProps({
  saldoPendiente: { type: String, required: true },
  // Opcional: { label, monto }[] a mostrar en la columna izquierda ("qué se
  // está cobrando"), inspirado en una foto de referencia del usuario (ver
  // docs/DECISIONES.md DEC-030) — puramente informativo, no participa del
  // cálculo ni se envía al backend.
  items: { type: Array, default: () => [] },
  // El selector de cliente solo tiene sentido cobrando el pedido completo
  // (factura 1:1 con el pedido) — no al pagar una sub-cuenta (ver
  // docs/DECISIONES.md DEC-026: la factura sigue siendo por pedido, no por
  // sub-cuenta), así que DividirCuentaModal no pasa estas props.
  permiteCliente: { type: Boolean, default: false },
  clienteActual: { type: Object, default: null },
  onBuscarClientes: { type: Function, default: null },
  onCrearCliente: { type: Function, default: null },
  onAsignarCliente: { type: Function, default: null },
});

const emit = defineEmits(['cerrar', 'confirmar']);

const medio = ref('efectivo');
const monto = ref(props.saldoPendiente);
const enviando = ref(false);

// Calculadora de vuelto (solo en efectivo) — puramente de UI para el
// cajero, nunca se envía al backend: lo único que se registra sigue
// siendo `monto` (lo que cubre del pedido), igual que antes.
const recibido = ref('');
const cambio = computed(() => {
  const m = parseFloat(monto.value) || 0;
  const r = parseFloat(recibido.value) || 0;
  return r > m ? (r - m).toFixed(2) : '0.00';
});

async function confirmar() {
  if (enviando.value) return;

  enviando.value = true;

  try {
    await emit('confirmar', { medio: medio.value, monto: monto.value });
  } finally {
    enviando.value = false;
  }
}
</script>

<template>
  <div class="fixed inset-0 z-20 flex items-end justify-center bg-black/40 sm:items-center">
    <div
      class="flex w-full flex-col overflow-hidden rounded-t-2xl bg-surface sm:rounded-2xl"
      :class="items.length > 0 ? 'max-w-2xl sm:flex-row' : 'max-w-md'"
    >
      <!-- Columna izquierda: qué se está cobrando (solo si el llamador la pasa) -->
      <div
        v-if="items.length > 0"
        class="max-h-40 overflow-y-auto border-b border-border p-6 sm:max-h-none sm:w-64 sm:shrink-0 sm:border-b-0 sm:border-r"
      >
        <h3 class="mb-3 text-sm font-semibold text-text-muted">Se está cobrando</h3>
        <ul class="space-y-2">
          <li v-for="(item, i) in items" :key="i" class="flex items-start justify-between gap-2 text-sm">
            <span class="text-text">{{ item.label }}</span>
            <span class="shrink-0 text-text-muted">${{ item.monto }}</span>
          </li>
        </ul>
      </div>

      <!-- Columna derecha: formulario de cobro -->
      <div class="flex-1 p-6">
        <h2 class="mb-4 text-xl font-semibold text-text">Cobrar</h2>

        <p class="mb-4 text-text-muted">
          Saldo pendiente: <span class="font-semibold text-text">${{ saldoPendiente }}</span>
        </p>

        <ClienteSelector
          v-if="permiteCliente"
          :cliente-actual="clienteActual"
          :on-buscar="onBuscarClientes"
          :on-crear="onCrearCliente"
          :on-asignar="onAsignarCliente"
        />

        <label class="mb-1 block text-sm font-medium text-text-muted">Medio de pago</label>
        <div class="mb-4 grid grid-cols-3 gap-2">
          <button
            v-for="opcion in [['efectivo', 'Efectivo'], ['tarjeta', 'Tarjeta'], ['transferencia', 'Transferencia']]"
            :key="opcion[0]"
            type="button"
            class="rounded-xl border-2 py-3 text-sm font-medium"
            :class="medio === opcion[0] ? 'border-primary bg-primary text-white' : 'border-border text-text'"
            @click="medio = opcion[0]"
          >
            {{ opcion[1] }}
          </button>
        </div>

        <label class="mb-1 block text-sm font-medium text-text-muted">Monto</label>
        <!--
          type="text" + inputmode="decimal" a propósito, no type="number":
          con configuración regional en español, un <input type="number">
          MUESTRA el valor con coma decimal ("4,50") aunque por dentro siga
          siendo "4.50" — confunde al cajero si intenta corregirlo a mano.
          Aquí se normaliza la coma a punto al escribir, sin depender del
          formato nativo del navegador.
        -->
        <input
          :value="monto"
          type="text"
          inputmode="decimal"
          class="w-full rounded-xl border border-border bg-surface px-4 py-3 text-lg text-text"
          :class="medio === 'efectivo' ? 'mb-4' : 'mb-6'"
          @input="monto = $event.target.value.replace(',', '.')"
        >

        <!-- Calculadora de vuelto — solo tiene sentido en efectivo -->
        <template v-if="medio === 'efectivo'">
          <label class="mb-1 block text-sm font-medium text-text-muted">Recibido del cliente</label>
          <input
            :value="recibido"
            type="text"
            inputmode="decimal"
            placeholder="0.00"
            class="mb-3 w-full rounded-xl border border-border bg-surface px-4 py-3 text-lg text-text"
            @input="recibido = $event.target.value.replace(',', '.')"
          >
          <p class="mb-6 flex items-center justify-between rounded-xl bg-surface-soft px-4 py-3">
            <span class="text-sm font-medium text-text-muted">Cambio a devolver</span>
            <span class="text-lg font-semibold text-text">${{ cambio }}</span>
          </p>
        </template>

        <div class="flex gap-3">
          <button
            type="button"
            class="flex-1 rounded-xl border border-border py-4 text-lg font-medium text-text"
            @click="emit('cerrar')"
          >
            Cancelar
          </button>
          <button
            type="button"
            :disabled="enviando"
            class="flex-1 rounded-xl bg-success py-4 text-lg font-semibold text-white disabled:opacity-50"
            @click="confirmar"
          >
            {{ enviando ? 'Procesando…' : 'Confirmar pago' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
