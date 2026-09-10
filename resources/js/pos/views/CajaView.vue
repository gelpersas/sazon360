<script setup>
import { onMounted, ref } from 'vue';
import { useAuthStore } from '../stores/auth';
import { useCajaStore } from '../stores/caja';

const auth = useAuthStore();
const caja = useCajaStore();

const cargando = ref(true);
const error = ref(null);
const accionEnCurso = ref(false);

const montoInicial = ref('');
const notaApertura = ref('');

const mostrarMovimiento = ref(false);
const tipoMovimiento = ref('ingreso');
const montoMovimiento = ref('');
const descripcionMovimiento = ref('');

const mostrarCierre = ref(false);
const montoReal = ref('');
const notaCierre = ref('');

onMounted(async () => {
  try {
    await caja.cargar(auth.sedeActualId);
  } catch {
    error.value = 'No se pudo cargar el estado de la caja.';
  } finally {
    cargando.value = false;
  }
});

async function abrirCaja() {
  if (accionEnCurso.value) return;

  if (!montoInicial.value.trim()) {
    error.value = 'Ingresa el monto inicial de caja.';
    return;
  }

  accionEnCurso.value = true;

  try {
    await caja.abrir(auth.sedeActualId, { montoInicial: montoInicial.value, nota: notaApertura.value.trim() || null });
    montoInicial.value = '';
    notaApertura.value = '';
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudo abrir la caja.';
  } finally {
    accionEnCurso.value = false;
  }
}

function abrirModalMovimiento(tipo) {
  tipoMovimiento.value = tipo;
  montoMovimiento.value = '';
  descripcionMovimiento.value = '';
  mostrarMovimiento.value = true;
}

async function confirmarMovimiento() {
  if (accionEnCurso.value) return;

  if (!montoMovimiento.value.trim() || !descripcionMovimiento.value.trim()) {
    error.value = 'Completa el monto y la descripción del movimiento.';
    return;
  }

  accionEnCurso.value = true;

  try {
    await caja.registrarMovimiento({
      tipo: tipoMovimiento.value,
      monto: montoMovimiento.value,
      descripcion: descripcionMovimiento.value.trim(),
    });
    mostrarMovimiento.value = false;
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudo registrar el movimiento.';
  } finally {
    accionEnCurso.value = false;
  }
}

function abrirModalCierre() {
  montoReal.value = '';
  notaCierre.value = '';
  mostrarCierre.value = true;
}

async function confirmarCierre() {
  if (accionEnCurso.value) return;

  if (!montoReal.value.trim()) {
    error.value = 'Ingresa el monto real contado.';
    return;
  }

  accionEnCurso.value = true;

  try {
    const cerrada = await caja.cerrar({ montoReal: montoReal.value, nota: notaCierre.value.trim() || null });
    mostrarCierre.value = false;
    window.alert(`Caja cerrada. Diferencia: $${cerrada.diferencia}`);
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudo cerrar la caja.';
  } finally {
    accionEnCurso.value = false;
  }
}
</script>

<template>
  <div class="min-h-screen bg-bg p-4 pb-8">
    <h1 class="mb-4 text-xl font-semibold text-text">Caja</h1>

    <p v-if="error" class="mb-4 rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger" role="alert">
      {{ error }}
      <button class="ml-2 underline" @click="error = null">cerrar</button>
    </p>

    <p v-if="cargando" class="text-center text-text-muted">Cargando…</p>

    <!-- Sin caja abierta: formulario de apertura -->
    <div v-else-if="!caja.actual" class="mx-auto max-w-md rounded-2xl bg-surface p-6 shadow">
      <p class="mb-4 text-text-muted">No hay una caja abierta en esta sede. Abre un turno para empezar a cobrar.</p>

      <label class="mb-1 block text-sm font-medium text-text-muted">Monto inicial (fondo de caja)</label>
      <input
        :value="montoInicial"
        type="text"
        inputmode="decimal"
        placeholder="0.00"
        class="mb-3 w-full rounded-xl border border-border bg-surface px-4 py-3 text-lg text-text"
        @input="montoInicial = $event.target.value.replace(',', '.')"
      >

      <label class="mb-1 block text-sm font-medium text-text-muted">Nota de apertura (opcional)</label>
      <textarea v-model="notaApertura" rows="2" class="mb-4 w-full rounded-xl border border-border bg-surface px-4 py-3 text-text" />

      <button
        class="w-full rounded-xl bg-success py-4 text-lg font-semibold text-white disabled:opacity-50"
        :disabled="accionEnCurso"
        @click="abrirCaja"
      >
        {{ accionEnCurso ? 'Abriendo…' : 'Abrir caja' }}
      </button>
    </div>

    <!-- Caja abierta -->
    <div v-else class="mx-auto max-w-2xl space-y-4">
      <div class="rounded-2xl bg-surface p-6 shadow">
        <p class="text-sm text-text-muted">Fondo inicial: ${{ caja.actual.monto_inicial }}</p>
        <p class="text-2xl font-semibold text-text">${{ caja.actual.monto_esperado }}</p>
        <p class="text-xs text-text-muted">Esperado en caja ahora mismo</p>
      </div>

      <div class="flex gap-2">
        <button class="flex-1 rounded-xl bg-success py-3 font-medium text-white" @click="abrirModalMovimiento('ingreso')">
          + Ingreso
        </button>
        <button class="flex-1 rounded-xl bg-danger py-3 font-medium text-white" @click="abrirModalMovimiento('egreso')">
          - Egreso
        </button>
      </div>

      <div class="rounded-2xl bg-surface p-4 shadow">
        <h2 class="mb-2 text-sm font-semibold text-text-muted">Movimientos del turno</h2>
        <ul class="divide-y divide-border">
          <li v-for="m in caja.actual.movimientos" :key="m.id" class="flex items-center justify-between py-2">
            <div>
              <p class="text-text">{{ m.descripcion }}</p>
              <p class="text-xs" :class="m.tipo === 'ingreso' ? 'text-success' : 'text-danger'">
                {{ m.tipo === 'ingreso' ? 'Ingreso' : 'Egreso' }}
              </p>
            </div>
            <span class="font-medium" :class="m.tipo === 'ingreso' ? 'text-success' : 'text-danger'">
              {{ m.tipo === 'ingreso' ? '+' : '-' }}${{ m.monto }}
            </span>
          </li>
          <p v-if="caja.actual.movimientos.length === 0" class="py-2 text-sm text-text-muted">Sin movimientos todavía.</p>
        </ul>
      </div>

      <button class="w-full rounded-xl border border-danger py-4 font-medium text-danger" @click="abrirModalCierre">
        Cerrar caja
      </button>
    </div>

    <!-- Modal: registrar movimiento -->
    <div v-if="mostrarMovimiento" class="fixed inset-0 z-20 flex items-end justify-center bg-black/40 sm:items-center">
      <div class="w-full max-w-md rounded-t-2xl bg-surface p-6 sm:rounded-2xl">
        <h2 class="mb-4 text-xl font-semibold text-text">
          {{ tipoMovimiento === 'ingreso' ? 'Registrar ingreso' : 'Registrar egreso' }}
        </h2>

        <label class="mb-1 block text-sm font-medium text-text-muted">Monto</label>
        <input
          :value="montoMovimiento"
          type="text"
          inputmode="decimal"
          placeholder="0.00"
          class="mb-3 w-full rounded-xl border border-border bg-surface px-4 py-3 text-lg text-text"
          @input="montoMovimiento = $event.target.value.replace(',', '.')"
        >

        <label class="mb-1 block text-sm font-medium text-text-muted">Descripción</label>
        <input
          v-model="descripcionMovimiento"
          type="text"
          placeholder="ej. Retiro para vueltos"
          class="mb-6 w-full rounded-xl border border-border bg-surface px-4 py-3 text-text"
        >

        <p v-if="error" class="mb-3 text-sm text-danger" role="alert">{{ error }}</p>

        <div class="flex gap-3">
          <button class="flex-1 rounded-xl border border-border py-4 text-lg font-medium text-text" @click="mostrarMovimiento = false">
            Cancelar
          </button>
          <button
            class="flex-1 rounded-xl bg-success py-4 text-lg font-semibold text-white disabled:opacity-50"
            :disabled="accionEnCurso"
            @click="confirmarMovimiento"
          >
            {{ accionEnCurso ? 'Guardando…' : 'Registrar' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal: cerrar caja -->
    <!-- caja.actual en la guarda: cerrar() lo pone en null antes de que
         mostrarCierre se apague, así que sin esto el template intenta leer
         caja.actual.monto_esperado en el instante entre ambos cambios. -->
    <div v-if="mostrarCierre && caja.actual" class="fixed inset-0 z-20 flex items-end justify-center bg-black/40 sm:items-center">
      <div class="w-full max-w-md rounded-t-2xl bg-surface p-6 sm:rounded-2xl">
        <h2 class="mb-4 text-xl font-semibold text-text">Cerrar caja</h2>

        <p class="mb-4 text-text-muted">
          Esperado según movimientos: <span class="font-semibold text-text">${{ caja.actual.monto_esperado }}</span>
        </p>

        <label class="mb-1 block text-sm font-medium text-text-muted">Monto real contado</label>
        <input
          :value="montoReal"
          type="text"
          inputmode="decimal"
          placeholder="0.00"
          class="mb-3 w-full rounded-xl border border-border bg-surface px-4 py-3 text-lg text-text"
          @input="montoReal = $event.target.value.replace(',', '.')"
        >

        <label class="mb-1 block text-sm font-medium text-text-muted">Nota de cierre (opcional)</label>
        <textarea v-model="notaCierre" rows="2" class="mb-6 w-full rounded-xl border border-border bg-surface px-4 py-3 text-text" />

        <p v-if="error" class="mb-3 text-sm text-danger" role="alert">{{ error }}</p>

        <div class="flex gap-3">
          <button class="flex-1 rounded-xl border border-border py-4 text-lg font-medium text-text" @click="mostrarCierre = false">
            Cancelar
          </button>
          <button
            class="flex-1 rounded-xl bg-danger py-4 text-lg font-semibold text-white disabled:opacity-50"
            :disabled="accionEnCurso"
            @click="confirmarCierre"
          >
            {{ accionEnCurso ? 'Cerrando…' : 'Confirmar cierre' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
