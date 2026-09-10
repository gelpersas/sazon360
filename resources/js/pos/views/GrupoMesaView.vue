<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useGrupoMesaStore } from '../stores/grupoMesa';
import { usePedidoStore } from '../stores/pedido';
import { useSubCuentaStore } from '../stores/subCuenta';
import PagoModal from '../components/PagoModal.vue';
import DividirCuentaModal from '../components/DividirCuentaModal.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const grupos = useGrupoMesaStore();
const pedidos = usePedidoStore();
const subCuentas = useSubCuentaStore();

const cargando = ref(true);
const error = ref(null);
const numeroPersonas = ref(2);
const disolviendo = ref(false);
const accionEnCurso = ref(false);

// Cobro directo desde el grupo (sin pasar por PosView) — ver
// docs/DECISIONES.md DEC-036.
const mostrarPago = ref(false);
const cobrando = ref(false);

// Dividir la cuenta de una mesa del grupo sin salir de esta pantalla — antes
// "Ver pedido"/"Cobrar" solo dejaban pagar el pedido completo de una mesa;
// para repartirlo por producto/persona había que entrar al carrito completo
// en PosView y buscar "Dividir" ahí, perdiendo el contexto del grupo (mismo
// hallazgo de navegación que ya resolvió DEC-059 para agregar ítems — ver
// docs/DECISIONES.md).
const mostrarDividirCuenta = ref(false);

// Sumar una mesa más al grupo — mismo hallazgo que en PosView (ver
// docs/DECISIONES.md): antes solo se podía armar el grupo completo de
// entrada, nunca hacerlo crecer después. Depende de que `pedidos.mesas` ya
// esté cargado (siempre lo está: a esta pantalla solo se llega navegando
// desde PosView, que lo carga al entrar).
const mostrarAgregarMesa = ref(false);
const mesaParaAgregar = ref(null);
const errorAgregarMesa = ref(null);

const mesasDisponiblesParaAgregar = computed(() => pedidos.mesas.filter((m) => !m.grupo_mesa_id));

// Mesas del grupo sin pedido abierto todavía — antes quedaban sin mostrarse
// en ningún lado ("ninguna mesa tiene pedido, vuelve al POS" solo cubría el
// caso de que NINGUNA tuviera, no el caso mixto). Solo aplica en modo
// Independiente: en modo General las mesas no principales nunca tienen
// pedido propio a propósito (comparten el del grupo), no es que les "falte".
const mesasSinPedido = computed(() => {
  if (!grupos.grupoActivo || grupos.grupoActivo.modo === 'general') return [];

  const idsConPedido = new Set(grupos.grupoActivo.pedidos.map((p) => p.mesa_id));
  return grupos.grupoActivo.mesas.filter((m) => !idsConPedido.has(m.id));
});

// Modo General: a lo sumo un pedido (el compartido, contra la mesa
// principal — ver Pedido::abrir()/ModoGrupoMesa).
const pedidoGeneral = computed(() => grupos.grupoActivo?.pedidos[0] ?? null);
const nombresMesas = computed(() => grupos.grupoActivo?.mesas.map((m) => m.nombre).join(' + ') ?? '');

const itemsParaCobro = computed(
  () => pedidos.pedidoActivo?.items.map((i) => ({ label: `${i.cantidad}× ${i.nombre_producto}`, monto: i.subtotal })) ?? [],
);

const porPersona = computed(() => {
  const total = parseFloat(grupos.grupoActivo?.total_combinado ?? '0');
  const n = Number(numeroPersonas.value) || 1;

  return (total / n).toFixed(2);
});

function nombreMesa(mesaId) {
  return grupos.grupoActivo?.mesas.find((m) => m.id === mesaId)?.nombre ?? `Mesa #${mesaId}`;
}

onMounted(async () => {
  try {
    await grupos.cargarGrupo(route.params.id);

    // Defensivo: normalmente ya viene cargado desde PosView, pero si se
    // entra aquí directo (ej. refrescar la página en esta ruta), sin esto
    // el picker de "+ Agregar mesa" se vería vacío aunque haya mesas libres.
    if (pedidos.mesas.length === 0) await pedidos.cargarReferencia(auth.sedeActualId);
  } catch {
    error.value = 'No se pudo cargar este grupo de mesas.';
  } finally {
    cargando.value = false;
  }
});

async function irACobrarPedido(pedidoId) {
  await pedidos.seleccionarPedido(pedidoId);
  router.push({ name: 'pos' });
}

// Antes las mesas del grupo sin pedido todavía simplemente no aparecían en
// ningún lado — esto las hace accionables directo desde el resumen del
// grupo, sin tener que adivinar cuál falta desde la lista plana del POS.
async function tomarPedido(mesaId) {
  if (accionEnCurso.value) return;
  accionEnCurso.value = true;

  try {
    await pedidos.abrirPedido(auth.sedeActualId, { tipo: 'mesa', mesaId });
    router.push({ name: 'pos' });
  } catch {
    error.value = 'No se pudo abrir el pedido de esa mesa.';
  } finally {
    accionEnCurso.value = false;
  }
}

function abrirModalAgregarMesa() {
  mesaParaAgregar.value = null;
  errorAgregarMesa.value = null;
  mostrarAgregarMesa.value = true;
}

async function confirmarAgregarMesa() {
  if (accionEnCurso.value || !mesaParaAgregar.value) return;
  accionEnCurso.value = true;
  errorAgregarMesa.value = null;

  try {
    await grupos.agregarMesa(grupos.grupoActivo.id, mesaParaAgregar.value);
    await pedidos.cargarReferencia(auth.sedeActualId); // refresca grupo_mesa_id de las mesas
    mostrarAgregarMesa.value = false;
  } catch (e) {
    errorAgregarMesa.value = e.response?.data?.message ?? 'No se pudo agregar esa mesa al grupo.';
  } finally {
    accionEnCurso.value = false;
  }
}

async function abrirCobroDirecto(pedidoId) {
  if (cobrando.value) return; // prevención de doble toque

  cobrando.value = true;

  try {
    await pedidos.seleccionarPedido(pedidoId);
    mostrarPago.value = true;
  } catch {
    error.value = 'No se pudo cargar ese pedido.';
  } finally {
    cobrando.value = false;
  }
}

async function abrirDividirCuenta(pedidoId) {
  if (accionEnCurso.value) return;
  accionEnCurso.value = true;

  try {
    await pedidos.seleccionarPedido(pedidoId);
    await subCuentas.cargar(pedidoId);
    mostrarDividirCuenta.value = true;
  } catch {
    error.value = 'No se pudo cargar ese pedido.';
  } finally {
    accionEnCurso.value = false;
  }
}

// Mismo criterio que en PosView.vue: se pasan como props (no eventos) a
// DividirCuentaModal para que el modal reciba el rechazo real de la
// promesa y muestre el mensaje donde está el formulario, ver el comentario
// en components/DividirCuentaModal.vue.
async function crearSubCuenta({ nombre, asignaciones }) {
  await subCuentas.crear(pedidos.pedidoActivo.id, nombre, asignaciones);
}

async function eliminarSubCuenta(sub) {
  if (!confirm(`¿Eliminar la sub-cuenta "${sub.nombre}"?`)) return;

  await subCuentas.eliminar(sub.id);
}

async function pagarSubCuenta({ subCuenta, medio, monto }) {
  await subCuentas.pagar(subCuenta.id, { medio, monto });
  await pedidos.seleccionarPedido(pedidos.pedidoActivo.id); // refresca el total/estado del pedido
  await grupos.cargarGrupo(grupos.grupoActivo.id); // refresca el saldo de esta mesa en la tarjeta del grupo
}

async function confirmarPagoDirecto({ medio, monto }) {
  try {
    await pedidos.pagar({ medio, monto });

    if (pedidos.pedidoActivo.estado === 'cobrado') {
      mostrarPago.value = false;
      pedidos.cerrarPedidoActivo();
    }

    await grupos.cargarGrupo(grupos.grupoActivo.id); // refresca totales/estado del grupo
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se pudo registrar el pago.';
  }
}

async function disolverGrupo() {
  if (disolviendo.value) return; // prevención de doble toque
  if (!confirm('¿Disolver este grupo? Las mesas vuelven a operar por separado (los pedidos no se tocan).')) return;

  disolviendo.value = true;

  try {
    await grupos.disolver(grupos.grupoActivo.id);
    router.push({ name: 'pos' });
  } catch {
    error.value = 'No se pudo disolver el grupo.';
  } finally {
    disolviendo.value = false;
  }
}
</script>

<template>
  <div class="min-h-screen bg-bg p-4 pb-24">
    <div class="mb-4 flex items-center gap-2">
      <svg class="h-5 w-5 text-primary" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
        <path d="M7 13a4 4 0 0 1 0-6h2M13 7a4 4 0 0 1 0 6h-2" />
        <line x1="7" y1="10" x2="13" y2="10" />
      </svg>
      <h1 class="text-xl font-semibold text-text">Mesas unidas</h1>
    </div>

    <p v-if="error" class="mb-4 rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger" role="alert">{{ error }}</p>
    <p v-if="cargando" class="text-center text-text-muted">Cargando…</p>

    <div v-else-if="grupos.grupoActivo" class="flex flex-col gap-4 md:flex-row md:items-start">
      <!-- Resumen del grupo: fijo en pantallas anchas mientras se recorren
           los pedidos, mismo patrón que el carrito de PosView — antes todo
           esto y la lista de pedidos vivían en una sola columna angosta,
           desperdiciando el ancho disponible en tablet/escritorio. -->
      <aside class="space-y-4 md:sticky md:top-4 md:w-72 md:shrink-0">
        <div class="overflow-hidden rounded-2xl border border-primary/20 bg-surface shadow">
          <div class="flex flex-wrap gap-1.5 bg-primary/10 px-4 py-2.5">
            <span
              v-for="mesa in grupos.grupoActivo.mesas"
              :key="mesa.id"
              class="rounded-full bg-surface px-2.5 py-1 text-xs font-medium text-text shadow-sm"
            >
              {{ mesa.nombre }}
            </span>
          </div>
          <div class="px-4 py-4">
            <p class="text-2xl font-semibold text-text">${{ grupos.grupoActivo.total_combinado }}</p>
            <p class="text-xs text-text-muted">
              {{ grupos.grupoActivo.modo === 'general' ? 'Pedido general del grupo' : 'Total combinado del grupo' }}
            </p>
          </div>
        </div>

        <div class="rounded-2xl bg-surface p-4 shadow">
          <label class="mb-2 block text-sm font-medium text-text-muted">Dividir en partes iguales entre</label>
          <div class="flex items-center gap-3">
            <input
              v-model="numeroPersonas"
              type="number"
              min="1"
              inputmode="numeric"
              class="w-20 rounded-xl border border-border bg-surface px-3 py-2 text-lg text-text"
            >
            <span class="text-text-muted">personas</span>
          </div>
          <p class="mt-2 text-lg font-semibold text-success">${{ porPersona }} c/u</p>
          <p class="text-xs text-text-muted">
            Solo de referencia — {{ grupos.grupoActivo.modo === 'general' ? 'usa "Dividir" para cobrar por persona' : 'el cobro se registra por mesa' }}.
          </p>
        </div>

        <button
          class="w-full rounded-xl border border-danger py-3 font-medium text-danger disabled:opacity-50"
          :disabled="disolviendo"
          @click="disolverGrupo"
        >
          {{ disolviendo ? 'Disolviendo…' : 'Disolver grupo' }}
        </button>
      </aside>

      <!-- Pedidos del grupo: en grilla, no apilados — con 3+ mesas unidas se
           aprovecha el ancho en vez de obligar a bajar toda la pantalla. -->
      <section class="min-w-0 flex-1">
        <div class="mb-2 flex items-center justify-between gap-2">
          <h2 class="text-sm font-semibold text-text-muted">
            {{ grupos.grupoActivo.modo === 'general' ? 'Pedido del grupo' : 'Pedidos del grupo' }}
          </h2>
          <button class="text-xs font-medium text-text-muted underline" @click="abrirModalAgregarMesa">
            + Agregar mesa
          </button>
        </div>

        <!-- Modo General: un solo pedido compartido por todas las mesas —
             ver App\Enums\ModoGrupoMesa. No hay "mesas sin pedido" que
             mostrar aparte: las no-principales comparten este a propósito. -->
        <template v-if="grupos.grupoActivo.modo === 'general'">
          <div
            v-if="pedidoGeneral"
            class="max-w-sm rounded-2xl border-l-4 bg-surface p-4 shadow"
            :class="pedidoGeneral.saldo_pendiente !== '0.00' ? 'border-l-warning' : 'border-l-success'"
          >
            <div class="mb-1 flex items-center justify-between gap-2">
              <span class="font-medium text-text">{{ nombresMesas }}</span>
              <span class="shrink-0 text-text">${{ pedidoGeneral.total }}</span>
            </div>
            <p v-if="pedidoGeneral.saldo_pendiente !== '0.00'" class="mb-3 flex items-center gap-1 text-sm text-warning">
              <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5">
                <circle cx="10" cy="10" r="7" />
                <path d="M10 6.5v3.5l2.5 1.5" stroke-linecap="round" />
              </svg>
              Pendiente: ${{ pedidoGeneral.saldo_pendiente }}
            </p>
            <p v-else class="mb-3 flex items-center gap-1 text-sm text-success">
              <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="4,10 8,14 16,6" />
              </svg>
              Pagado
            </p>

            <div class="flex gap-2">
              <button
                class="flex-1 rounded-xl border border-border py-2 text-sm font-medium text-text active:bg-surface-soft"
                @click="irACobrarPedido(pedidoGeneral.id)"
              >
                Ver pedido
              </button>
              <button
                v-if="pedidoGeneral.saldo_pendiente !== '0.00'"
                class="rounded-xl border border-border px-3 py-2 text-sm font-medium text-text active:bg-surface-soft"
                @click="abrirDividirCuenta(pedidoGeneral.id)"
              >
                Dividir
              </button>
              <button
                v-if="pedidoGeneral.saldo_pendiente !== '0.00' && auth.puedeVerCaja"
                class="flex-1 rounded-xl bg-success py-2 text-sm font-semibold text-white disabled:opacity-50"
                :disabled="cobrando"
                @click="abrirCobroDirecto(pedidoGeneral.id)"
              >
                Cobrar
              </button>
            </div>
          </div>
          <button
            v-else
            class="flex max-w-sm items-center justify-between rounded-2xl border-2 border-dashed border-border p-4 text-left"
            :disabled="accionEnCurso"
            @click="tomarPedido(grupos.grupoActivo.mesa_principal.id)"
          >
            <span class="font-medium text-text">{{ nombresMesas }}</span>
            <span class="flex items-center gap-1 text-sm text-text-muted">
              Tomar pedido del grupo
              <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="4" y1="10" x2="15" y2="10" />
                <polyline points="11,6 15,10 11,14" />
              </svg>
            </span>
          </button>
        </template>

        <!-- Modo Independiente (default): cada mesa su propio pedido -->
        <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
          <div
            v-for="pedido in grupos.grupoActivo.pedidos"
            :key="pedido.id"
            class="rounded-2xl border-l-4 bg-surface p-4 shadow"
            :class="pedido.saldo_pendiente !== '0.00' ? 'border-l-warning' : 'border-l-success'"
          >
            <div class="mb-1 flex items-center justify-between">
              <span class="font-medium text-text">{{ nombreMesa(pedido.mesa_id) }}</span>
              <span class="text-text">${{ pedido.total }}</span>
            </div>
            <p v-if="pedido.saldo_pendiente !== '0.00'" class="mb-3 flex items-center gap-1 text-sm text-warning">
              <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5">
                <circle cx="10" cy="10" r="7" />
                <path d="M10 6.5v3.5l2.5 1.5" stroke-linecap="round" />
              </svg>
              Pendiente: ${{ pedido.saldo_pendiente }}
            </p>
            <p v-else class="mb-3 flex items-center gap-1 text-sm text-success">
              <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="4,10 8,14 16,6" />
              </svg>
              Pagado
            </p>

            <div class="flex gap-2">
              <button
                class="flex-1 rounded-xl border border-border py-2 text-sm font-medium text-text active:bg-surface-soft"
                @click="irACobrarPedido(pedido.id)"
              >
                Ver pedido
              </button>
              <button
                v-if="pedido.saldo_pendiente !== '0.00'"
                class="rounded-xl border border-border px-3 py-2 text-sm font-medium text-text active:bg-surface-soft"
                @click="abrirDividirCuenta(pedido.id)"
              >
                Dividir
              </button>
              <button
                v-if="pedido.saldo_pendiente !== '0.00' && auth.puedeVerCaja"
                class="flex-1 rounded-xl bg-success py-2 text-sm font-semibold text-white disabled:opacity-50"
                :disabled="cobrando"
                @click="abrirCobroDirecto(pedido.id)"
              >
                Cobrar
              </button>
            </div>
          </div>

          <!-- Mesas del grupo que todavía no tienen carrito propio — antes
               no aparecían en ningún lado, había que adivinar cuál faltaba
               desde la lista plana del POS. -->
          <button
            v-for="mesa in mesasSinPedido"
            :key="mesa.id"
            class="flex items-center justify-between rounded-2xl border-2 border-dashed border-border p-4 text-left"
            :disabled="accionEnCurso"
            @click="tomarPedido(mesa.id)"
          >
            <span class="font-medium text-text">{{ mesa.nombre }}</span>
            <span class="flex items-center gap-1 text-sm text-text-muted">
              Tomar pedido
              <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="4" y1="10" x2="15" y2="10" />
                <polyline points="11,6 15,10 11,14" />
              </svg>
            </span>
          </button>
        </div>
      </section>
    </div>

    <!-- Agregar una mesa más al grupo -->
    <div v-if="mostrarAgregarMesa" class="fixed inset-0 z-20 flex items-center justify-center bg-black/40">
      <div class="w-full max-w-sm rounded-2xl bg-surface p-6">
        <h2 class="mb-4 text-lg font-semibold text-text">¿Qué mesa quieres sumar al grupo?</h2>
        <div class="mb-4 grid grid-cols-3 gap-2">
          <button
            v-for="mesa in mesasDisponiblesParaAgregar"
            :key="mesa.id"
            class="rounded-xl border-2 p-3 text-sm font-medium"
            :class="mesaParaAgregar === mesa.id ? 'border-success bg-success-soft text-success' : 'border-border text-text'"
            @click="mesaParaAgregar = mesa.id"
          >
            {{ mesa.nombre }}
          </button>
          <p v-if="mesasDisponiblesParaAgregar.length === 0" class="col-span-3 text-sm text-text-muted">
            No hay mesas libres para sumar.
          </p>
        </div>
        <p v-if="errorAgregarMesa" class="mb-3 text-sm text-danger" role="alert">{{ errorAgregarMesa }}</p>
        <div class="flex gap-2">
          <button
            class="flex-1 rounded-xl bg-success py-3 font-medium text-white disabled:opacity-50"
            :disabled="!mesaParaAgregar || accionEnCurso"
            @click="confirmarAgregarMesa"
          >
            Agregar
          </button>
          <button class="rounded-xl border border-border px-4 py-3 text-text-muted" @click="mostrarAgregarMesa = false">
            Cancelar
          </button>
        </div>
      </div>
    </div>

    <PagoModal
      v-if="mostrarPago && pedidos.pedidoActivo"
      :saldo-pendiente="pedidos.pedidoActivo.saldo_pendiente"
      :items="itemsParaCobro"
      :permite-cliente="auth.puedeVerCaja"
      :cliente-actual="pedidos.pedidoActivo.cliente"
      :on-buscar-clientes="(termino) => pedidos.buscarClientes(auth.sedeActualId, termino)"
      :on-crear-cliente="(datos) => pedidos.crearCliente(auth.sedeActualId, datos)"
      :on-asignar-cliente="pedidos.asignarCliente"
      @cerrar="mostrarPago = false"
      @confirmar="confirmarPagoDirecto"
    />

    <!-- Dividir la cuenta de una mesa del grupo, sin salir de esta pantalla
         (ver docs/DECISIONES.md) -->
    <DividirCuentaModal
      v-if="mostrarDividirCuenta && pedidos.pedidoActivo"
      :pedido="pedidos.pedidoActivo"
      :sub-cuentas="subCuentas.lista"
      :puede-cobrar="auth.puedeVerCaja"
      :on-crear="crearSubCuenta"
      :on-eliminar="eliminarSubCuenta"
      :on-pagar="pagarSubCuenta"
      @cerrar="mostrarDividirCuenta = false"
    />
  </div>
</template>
