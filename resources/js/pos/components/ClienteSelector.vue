<script setup>
import { computed, reactive, ref } from 'vue';

// Selector opcional de cliente "al cobrar" (ver docs/DECISIONES.md) — sin
// esto, la factura electrónica sale a nombre del "consumidor final"
// genérico (ver config/facturacion.php). onBuscar/onCrear/onAsignar se
// pasan como funciones (no eventos): igual que en DividirCuentaModal.vue,
// emit() no espera al handler real del padre, y acá sí hace falta esperar
// la respuesta del backend para mostrar errores o resultados de búsqueda.
const props = defineProps({
  clienteActual: { type: Object, default: null },
  onBuscar: { type: Function, required: true },
  onCrear: { type: Function, required: true },
  onAsignar: { type: Function, required: true },
});

const abierto = ref(false);
const modo = ref('buscar'); // 'buscar' | 'nuevo'
const termino = ref('');
const resultados = ref([]);
const buscando = ref(false);
const asignando = ref(false);
const error = ref(null);

const nuevoCliente = reactive({
  tipo_persona: 'natural',
  tipo_documento: '13',
  numero_documento: '',
  dv: '',
  nombres: '',
  apellidos: '',
  razon_social: '',
  nombre_comercial: '',
  direccion: '',
  telefono: '',
  email: '',
});

function abrir() {
  abierto.value = true;
  modo.value = 'buscar';
  termino.value = '';
  resultados.value = [];
  error.value = null;
}

function cerrar() {
  abierto.value = false;
}

async function buscar() {
  buscando.value = true;
  error.value = null;

  try {
    resultados.value = await props.onBuscar(termino.value);
  } catch {
    error.value = 'No se pudo buscar clientes.';
  } finally {
    buscando.value = false;
  }
}

async function elegir(cliente) {
  if (asignando.value) return;
  asignando.value = true;
  error.value = null;

  try {
    await props.onAsignar(cliente.id);
    cerrar();
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudo asignar el cliente.';
  } finally {
    asignando.value = false;
  }
}

async function quitar() {
  if (asignando.value) return;
  asignando.value = true;

  try {
    await props.onAsignar(null);
    cerrar();
  } catch {
    error.value = 'No se pudo quitar el cliente.';
  } finally {
    asignando.value = false;
  }
}

// Persona natural: nombres+apellidos. Jurídica: razón social. Nunca ambos a
// la vez — mismo criterio que el formulario del panel (ver
// App\Filament\Resources\Clientes\Schemas\ClienteForm, DEC-050).
const esJuridica = computed(() => nuevoCliente.tipo_persona === 'juridica');
const esNit = computed(() => nuevoCliente.tipo_documento === '31');

async function guardarYUsar() {
  if (asignando.value) return;

  const nombreCompleto = esJuridica.value
    ? nuevoCliente.razon_social.trim()
    : `${nuevoCliente.nombres.trim()} ${nuevoCliente.apellidos.trim()}`.trim();

  if (!nuevoCliente.numero_documento.trim() || !nombreCompleto) {
    error.value = esJuridica.value
      ? 'Completa al menos el documento y la razón social.'
      : 'Completa al menos el documento, el nombre y el apellido.';
    return;
  }

  asignando.value = true;
  error.value = null;

  try {
    const creado = await props.onCrear({ ...nuevoCliente });
    await props.onAsignar(creado.id);
    cerrar();
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudo crear el cliente.';
  } finally {
    asignando.value = false;
  }
}
</script>

<template>
  <div class="mb-4">
    <div class="flex items-center justify-between rounded-xl bg-surface-soft px-4 py-3">
      <div>
        <p class="text-xs font-medium text-text-muted">Cliente</p>
        <p class="text-sm font-semibold text-text">{{ clienteActual?.nombre ?? 'Consumidor final' }}</p>
      </div>
      <div class="flex gap-2">
        <button type="button" class="text-sm font-medium text-text-muted underline" @click="abrir">
          Cambiar
        </button>
        <button v-if="clienteActual" type="button" class="text-sm font-medium text-danger underline" @click="quitar">
          Quitar
        </button>
      </div>
    </div>

    <div v-if="abierto" class="mt-2 rounded-xl border border-border p-4">
      <div class="mb-3 flex gap-2">
        <button
          type="button"
          class="rounded-lg px-3 py-1.5 text-sm font-medium"
          :class="modo === 'buscar' ? 'bg-primary text-white' : 'bg-surface-soft text-text-muted'"
          @click="modo = 'buscar'"
        >
          Buscar
        </button>
        <button
          type="button"
          class="rounded-lg px-3 py-1.5 text-sm font-medium"
          :class="modo === 'nuevo' ? 'bg-primary text-white' : 'bg-surface-soft text-text-muted'"
          @click="modo = 'nuevo'"
        >
          + Nuevo cliente
        </button>
      </div>

      <template v-if="modo === 'buscar'">
        <div class="mb-2 flex gap-2">
          <input
            v-model="termino"
            type="search"
            placeholder="Nombre o documento…"
            class="flex-1 rounded-xl border border-border bg-surface px-4 py-3 text-base text-text"
            @keyup.enter="buscar"
          >
          <button type="button" class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white" @click="buscar">
            {{ buscando ? '…' : 'Buscar' }}
          </button>
        </div>

        <ul class="max-h-48 divide-y divide-border overflow-y-auto">
          <li v-for="cliente in resultados" :key="cliente.id">
            <button
              type="button"
              class="flex w-full items-center justify-between px-2 py-2 text-left text-sm hover:bg-surface-soft"
              :disabled="asignando"
              @click="elegir(cliente)"
            >
              <span class="text-text">{{ cliente.nombre }}</span>
              <span class="text-text-muted">{{ cliente.numero_documento }}</span>
            </button>
          </li>
          <p v-if="!buscando && resultados.length === 0" class="py-2 text-sm text-text-muted">
            Sin resultados todavía — escribe y busca.
          </p>
        </ul>
      </template>

      <template v-else>
        <!-- Mismo layout de 4 columnas que el formulario del panel (ver
             App\Filament\Resources\Clientes\Schemas\ClienteForm, DEC-050):
             documento + DV comparten línea (3/4 + 1/4), nombres/apellidos o
             razón social según el tipo, nombre comercial siempre opcional. -->
        <div class="grid grid-cols-4 gap-3">
          <select v-model="nuevoCliente.tipo_persona" class="col-span-2 rounded-xl border border-border bg-surface px-4 py-3 text-base text-text">
            <option value="natural">Persona natural</option>
            <option value="juridica">Persona jurídica</option>
          </select>
          <select v-model="nuevoCliente.tipo_documento" class="col-span-2 rounded-xl border border-border bg-surface px-4 py-3 text-base text-text">
            <option value="13">Cédula</option>
            <option value="31">NIT</option>
            <option value="22">Cédula extranjería</option>
            <option value="41">Pasaporte</option>
            <option value="91">NUIP</option>
          </select>

          <template v-if="!esJuridica">
            <input v-model="nuevoCliente.nombres" type="text" placeholder="Nombres" class="col-span-2 rounded-xl border border-border bg-surface px-4 py-3 text-base text-text">
            <input v-model="nuevoCliente.apellidos" type="text" placeholder="Apellidos" class="col-span-2 rounded-xl border border-border bg-surface px-4 py-3 text-base text-text">
          </template>
          <input v-else v-model="nuevoCliente.razon_social" type="text" placeholder="Razón social" class="col-span-4 rounded-xl border border-border bg-surface px-4 py-3 text-base text-text">

          <input v-model="nuevoCliente.nombre_comercial" type="text" placeholder="Nombre comercial (opcional)" class="col-span-4 rounded-xl border border-border bg-surface px-4 py-3 text-base text-text">

          <input v-model="nuevoCliente.numero_documento" type="text" placeholder="N.º de documento" class="col-span-3 rounded-xl border border-border bg-surface px-4 py-3 text-base text-text">
          <input
            v-if="esNit"
            v-model="nuevoCliente.dv"
            type="text"
            maxlength="1"
            placeholder="DV"
            class="col-span-1 rounded-xl border border-border bg-surface px-4 py-3 text-base text-text"
          >

          <input v-model="nuevoCliente.telefono" type="text" placeholder="Teléfono (opcional)" class="col-span-2 rounded-xl border border-border bg-surface px-4 py-3 text-base text-text">
          <input v-model="nuevoCliente.email" type="email" placeholder="Email (opcional)" class="col-span-2 rounded-xl border border-border bg-surface px-4 py-3 text-base text-text">
          <input v-model="nuevoCliente.direccion" type="text" placeholder="Dirección (opcional)" class="col-span-4 rounded-xl border border-border bg-surface px-4 py-3 text-base text-text">
        </div>

        <button
          type="button"
          class="mt-3 w-full rounded-xl bg-success py-2 text-sm font-semibold text-white disabled:opacity-50"
          :disabled="asignando"
          @click="guardarYUsar"
        >
          {{ asignando ? 'Guardando…' : 'Guardar y usar' }}
        </button>
      </template>

      <p v-if="error" class="mt-2 text-sm text-danger" role="alert">{{ error }}</p>

      <button type="button" class="mt-3 w-full text-center text-sm text-text-muted" @click="cerrar">
        Cancelar
      </button>
    </div>
  </div>
</template>
