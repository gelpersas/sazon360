<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { usePedidoStore } from '../stores/pedido';
import { useGrupoMesaStore } from '../stores/grupoMesa';
import { useSubCuentaStore } from '../stores/subCuenta';
import PagoModal from '../components/PagoModal.vue';
import DividirCuentaModal from '../components/DividirCuentaModal.vue';

const auth = useAuthStore();
const pedidos = usePedidoStore();
const grupos = useGrupoMesaStore();
const subCuentas = useSubCuentaStore();
const router = useRouter();

const cargando = ref(true);
const error = ref(null);
const tipoNuevo = ref(null);
const productoParaArea = ref(null); // producto en espera de elegir área (si hay más de una)
const mostrarPago = ref(false);
const mostrarDividirCuenta = ref(false);
const accionEnCurso = ref(false);

// Vista previa de un pedido de la lista sin entrar de verdad a su carrito —
// pedido explícito del usuario ("previsualizar el pedido rápido sin abrir el
// pedido"). Usa obtenerPedido() (no seleccionarPedido()): a propósito no
// toca pedidos.pedidoActivo, así que la pantalla se queda en la lista.
const pedidoPreview = ref(null);
const cargandoPreview = ref(false);

// Unión de mesas (Fase 9, ver docs/DECISIONES.md DEC-025) — el mismo modal
// sirve para 3 casos distintos, según desde dónde se abra (ver
// abrirModalUnirMesas()/abrirModalUnirDesdeMesaActiva() y docs/DECISIONES.md
// del hallazgo de que la unión solo se podía armar de antemano, nunca desde
// dentro de una mesa ya abierta ni sumar una mesa a un grupo ya en marcha):
//   1. Desde "Pedidos abiertos" (grupoParaUnir y mesaBaseParaUnir en null):
//      arma un grupo nuevo desde cero, mínimo 2 mesas elegidas.
//   2. Desde el carrito de una mesa SIN grupo (mesaBaseParaUnir = esa mesa):
//      arma un grupo nuevo con esa mesa + las que se elijan (mínimo 1 más).
//   3. Desde el carrito de una mesa YA agrupada (grupoParaUnir = ese grupo):
//      suma las mesas elegidas al grupo existente, sin crear uno nuevo.
const mostrarUnirMesas = ref(false);
const mesasParaUnir = ref([]);
const errorUnion = ref(null);
const grupoParaUnir = ref(null);
const mesaBaseParaUnir = ref(null);

// Modo del grupo NUEVO que se está por crear (ver docs/DECISIONES.md
// ModoGrupoMesa) — solo aplica al crear, no al sumar mesas a un grupo ya
// existente (grupoParaUnir no null): el modo se fija una vez y no cambia.
const modoParaUnir = ref('independiente');
const mostrarSelectorModo = computed(() => grupoParaUnir.value === null);

const areaUnica = computed(() => (pedidos.areas.length === 1 ? pedidos.areas[0] : null));
const hayItemsPendientes = computed(() => pedidos.pedidoActivo?.items.some((i) => !i.enviado) ?? false);
const mesasDisponiblesParaUnir = computed(
  () => pedidos.mesas.filter((m) => !m.grupo_mesa_id && m.id !== mesaBaseParaUnir.value),
);
const minimoMesasParaConfirmar = computed(() => (grupoParaUnir.value || mesaBaseParaUnir.value ? 1 : 2));
const tituloModalUnion = computed(() => {
  if (grupoParaUnir.value) return '¿Qué mesa quieres sumar al grupo?';
  if (mesaBaseParaUnir.value) return '¿Con qué otra mesa quieres unir esta?';
  return '¿Qué mesas quieres unir?';
});
const etiquetaBotonUnion = computed(() => (grupoParaUnir.value
  ? `Agregar (${mesasParaUnir.value.length})`
  : `Unir (${mesasParaUnir.value.length + (mesaBaseParaUnir.value ? 1 : 0)})`));

// Grupo al que pertenece la mesa del pedido activo (si la hay) — permite
// mostrar, dentro del propio carrito, un selector para saltar entre las
// mesas del mismo grupo sin salir a otra pantalla (ver docs/DECISIONES.md:
// antes había que volver a la lista y entrar mesa por mesa, perdiendo el
// contexto del grupo en cada vuelta).
const grupoDeMesaActiva = computed(() => {
  const grupoMesaId = pedidos.pedidoActivo?.mesa?.grupo_mesa_id;
  if (!grupoMesaId) return null;

  return grupos.grupos.find((g) => g.id === grupoMesaId) ?? null;
});

// Categoría activa del catálogo (Fase 11b, ver docs/DECISIONES.md DEC-028) —
// antes se mostraban todas las categorías apiladas; ahora el catálogo va en
// tabs y solo se pintan los productos de la categoría elegida.
const categoriaActivaId = ref(null);

watch(
  () => pedidos.categorias,
  (categorias) => {
    if (categorias.length > 0 && !categorias.some((c) => c.id === categoriaActivaId.value)) {
      categoriaActivaId.value = categorias[0].id;
    }
  },
  { immediate: true },
);

const categoriaActiva = computed(
  () => pedidos.categorias.find((c) => c.id === categoriaActivaId.value) ?? null,
);

// Buscador de productos: con catálogos grandes, recorrer categoría por
// categoría es lento — con texto escrito, busca por nombre en TODAS las
// categorías a la vez y deja de mostrar los tabs (ver docs/DECISIONES.md).
const busquedaProducto = ref('');

const productosDelCatalogo = computed(
  () => pedidos.categorias.flatMap((categoria) => categoria.productos),
);

const productosAMostrar = computed(() => {
  const termino = busquedaProducto.value.trim().toLowerCase();

  if (!termino) {
    return categoriaActiva.value?.productos ?? [];
  }

  return productosDelCatalogo.value.filter((producto) => producto.nombre.toLowerCase().includes(termino));
});

// "Pedidos abiertos" — filtro por tipo + buscador + tiempo transcurrido, para
// que mesero y cajero (comparten esta pantalla, ver docs/DECISIONES.md
// DEC-043) ubiquen un pedido concreto más rápido cuando hay varios activos a
// la vez, en vez de recorrer una sola grilla mezclada.
const TIPOS_PEDIDO = [['mesa', 'Mesa'], ['mostrador', 'Mostrador'], ['para_llevar', 'Para llevar'], ['domicilio', 'Domicilio']];
const etiquetaTipo = (tipo) => TIPOS_PEDIDO.find(([valor]) => valor === tipo)?.[1] ?? tipo;

const tipoFiltro = ref('todos');
const busquedaPedido = ref('');

const conteoPorTipo = computed(() => {
  const conteo = {};
  for (const p of pedidos.pedidosAbiertos) conteo[p.tipo] = (conteo[p.tipo] ?? 0) + 1;
  return conteo;
});

const pedidosAbiertosFiltrados = computed(() => {
  const termino = busquedaPedido.value.trim().toLowerCase();

  return pedidos.pedidosAbiertos.filter((p) => {
    if (tipoFiltro.value !== 'todos' && p.tipo !== tipoFiltro.value) return false;
    if (!termino) return true;

    return (
      (p.mesa?.nombre ?? '').toLowerCase().includes(termino) ||
      etiquetaTipo(p.tipo).toLowerCase().includes(termino) ||
      String(p.id).includes(termino)
    );
  });
});

// "hace Xm"/"hace Xh Ym" recalculado cada 30s (ref `ahora` que fuerza a los
// computed a releer Date.now()) — así el mesero ve de un vistazo cuál
// pedido lleva más tiempo abierto sin tener que recargar la pantalla.
const ahora = ref(Date.now());
let intervaloReloj = null;

function minutosTranscurridos(fechaIso) {
  void ahora.value; // dependencia reactiva explícita, para que Vue recalcule cada tick
  return Math.max(0, Math.floor((Date.now() - new Date(fechaIso).getTime()) / 60000));
}

function tiempoTranscurrido(fechaIso) {
  const minutos = minutosTranscurridos(fechaIso);

  if (minutos < 1) return 'recién';
  if (minutos < 60) return `hace ${minutos} min`;

  const horas = Math.floor(minutos / 60);
  const resto = minutos % 60;
  return `hace ${horas}h ${resto}min`;
}

// 20 min de referencia: más que eso sin cobrarse/atenderse suele significar
// que algo se está demorando — solo un aviso visual, no bloquea nada.
const UMBRAL_DEMORA_MINUTOS = 20;
const esPedidoDemorado = (fechaIso) => minutosTranscurridos(fechaIso) >= UMBRAL_DEMORA_MINUTOS;

// Ítems a mostrar en la columna izquierda de PagoModal (Fase de UX de
// cobro, ver docs/DECISIONES.md DEC-030) — solo informativo.
const itemsParaCobro = computed(
  () => pedidos.pedidoActivo?.items.map((i) => ({ label: `${i.cantidad}× ${i.nombre_producto}`, monto: i.subtotal })) ?? [],
);

onMounted(async () => {
  try {
    await pedidos.cargarReferencia(auth.sedeActualId);
    await pedidos.cargarPedidosAbiertos(auth.sedeActualId);
    await grupos.cargarGrupos(auth.sedeActualId);
  } catch {
    error.value = 'No se pudo cargar la información de la sede.';
  } finally {
    cargando.value = false;
  }

  intervaloReloj = setInterval(() => { ahora.value = Date.now(); }, 30000);
});

onUnmounted(() => {
  if (intervaloReloj) clearInterval(intervaloReloj);
});

function abrirModalUnirMesas() {
  grupoParaUnir.value = null;
  mesaBaseParaUnir.value = null;
  mesasParaUnir.value = [];
  modoParaUnir.value = 'independiente';
  errorUnion.value = null;
  mostrarUnirMesas.value = true;
}

// Entrada contextual pedida por el usuario: "al ingresar a mesa debo poder
// unir mesa con +" — antes la única forma de unir mesas era el botón de la
// lista, imposible de usar una vez ya dentro del carrito de una mesa.
function abrirModalUnirDesdeMesaActiva() {
  const mesa = pedidos.pedidoActivo?.mesa;
  if (!mesa) return;

  if (grupoDeMesaActiva.value) {
    grupoParaUnir.value = grupoDeMesaActiva.value;
    mesaBaseParaUnir.value = null;
  } else {
    grupoParaUnir.value = null;
    mesaBaseParaUnir.value = mesa.id;
  }

  mesasParaUnir.value = [];
  modoParaUnir.value = 'independiente';
  errorUnion.value = null;
  mostrarUnirMesas.value = true;
}

// Dentro del selector de mesas del grupo (ver template): si la mesa ya tiene
// pedido abierto en este grupo, solo cambia el carrito activo a esa mesa; si
// todavía no tiene, abre uno nuevo — para que "hacer los pedidos de la mesa
// unida" no obligue a salir de esta pantalla en ningún caso.
async function irAMesaDelGrupo(mesaId) {
  if (mesaId === pedidos.pedidoActivo?.mesa?.id) return;

  const pedidoExistente = grupoDeMesaActiva.value?.pedidos.find((p) => p.mesa_id === mesaId);

  if (pedidoExistente) {
    await pedidos.seleccionarPedido(pedidoExistente.id);
    return;
  }

  const grupoId = grupoDeMesaActiva.value?.id;
  tipoNuevo.value = 'mesa';
  await empezarPedido(mesaId);

  // El pedido recién creado todavía no aparece en grupos.grupos (se cargó
  // una sola vez al entrar a la pantalla) — sin refrescar, esa mesa seguiría
  // marcada "(nuevo)" en el selector aunque ya tenga carrito.
  if (grupoId) await grupos.cargarGrupos(auth.sedeActualId);
}

function alternarMesaParaUnir(mesaId) {
  const indice = mesasParaUnir.value.indexOf(mesaId);

  if (indice === -1) {
    mesasParaUnir.value.push(mesaId);
  } else {
    mesasParaUnir.value.splice(indice, 1);
  }
}

// Sub-cuentas (Fase 10) — se cargan/limpian junto con el pedido activo, no
// hace falta que cada punto que cambia pedidoActivo se acuerde de llamarlo.
watch(
  () => pedidos.pedidoActivo?.id,
  (pedidoId) => {
    if (pedidoId) {
      subCuentas.cargar(pedidoId);
    } else {
      subCuentas.limpiar();
    }
  },
  { immediate: true },
);

// Estas tres se pasan como props (no eventos) a DividirCuentaModal y no
// atrapan sus propios errores: el modal necesita el rechazo real de la
// promesa para mostrar el mensaje correcto donde está el formulario, en vez
// de un banner genérico arriba de la pantalla — ver comentario en
// components/DividirCuentaModal.vue.
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
}

async function confirmarUnion() {
  if (accionEnCurso.value) return; // prevención de doble toque
  accionEnCurso.value = true;
  errorUnion.value = null;

  const vieneDeCarritoActivo = grupoParaUnir.value !== null || mesaBaseParaUnir.value !== null;

  try {
    if (grupoParaUnir.value) {
      // Sumar mesas a un grupo que ya existe (una por una: el endpoint
      // agrega de a una, ver GrupoMesa::agregarMesa()).
      for (const mesaId of mesasParaUnir.value) {
        await grupos.agregarMesa(grupoParaUnir.value.id, mesaId);
      }
      await pedidos.cargarReferencia(auth.sedeActualId);
      mostrarUnirMesas.value = false;
    } else {
      const idsGrupo = mesaBaseParaUnir.value
        ? [mesaBaseParaUnir.value, ...mesasParaUnir.value]
        : mesasParaUnir.value;

      // Si la unión arranca desde una mesa con pedido en curso, esa debe
      // quedar como principal — importa en modo general (ver
      // GrupoMesa::unir()): de lo contrario el pedido ya empezado podría
      // quedar huérfano si la principal termina siendo otra mesa.
      const grupo = await grupos.unirMesas(auth.sedeActualId, idsGrupo, modoParaUnir.value, mesaBaseParaUnir.value);
      await pedidos.cargarReferencia(auth.sedeActualId); // refresca grupo_mesa_id de las mesas
      mostrarUnirMesas.value = false;

      // Si se armó desde la lista (sin mesa ancla), no hay carrito abierto
      // todavía — tiene sentido llevar al mesero directo al resumen del
      // grupo. Si se armó desde dentro de una mesa, mejor quedarse ahí: el
      // selector de mesas del grupo que aparece en el carrito ya alcanza
      // para moverse entre las mesas unidas sin salir de esta pantalla.
      if (!mesaBaseParaUnir.value) {
        router.push({ name: 'grupo-mesa', params: { id: grupo.id } });
      }
    }

    // pedidos.pedidoActivo.mesa quedó con el grupo_mesa_id de cuando se
    // cargó ese pedido — cargarReferencia() solo refresca pedidos.mesas
    // (la lista de referencia), no el objeto embebido en el pedido activo.
    // Sin este refresco, el selector de mesas del grupo no aparecería hasta
    // salir y volver a entrar al pedido.
    if (vieneDeCarritoActivo && pedidos.pedidoActivo) {
      await pedidos.seleccionarPedido(pedidos.pedidoActivo.id);
    }
  } catch (e) {
    errorUnion.value = e.response?.data?.message ?? 'No se pudo actualizar el grupo de mesas.';
  } finally {
    accionEnCurso.value = false;
  }
}

function elegirTipoNuevo(tipo) {
  if (tipo === 'mesa') {
    tipoNuevo.value = 'mesa';
    return;
  }

  tipoNuevo.value = tipo;
  empezarPedido();
}

async function empezarPedido(mesaId = null) {
  if (accionEnCurso.value) return;
  accionEnCurso.value = true;

  try {
    await pedidos.abrirPedido(auth.sedeActualId, { tipo: tipoNuevo.value, mesaId });
    tipoNuevo.value = null;
  } catch {
    error.value = 'No se pudo crear el pedido. Intenta de nuevo.';
  } finally {
    accionEnCurso.value = false;
  }
}

async function reanudar(pedidoId) {
  await pedidos.seleccionarPedido(pedidoId);
}

async function previsualizarPedido(pedidoId) {
  cargandoPreview.value = true;
  pedidoPreview.value = null;

  try {
    pedidoPreview.value = await pedidos.obtenerPedido(pedidoId);
  } catch {
    error.value = 'No se pudo cargar la vista previa del pedido.';
  } finally {
    cargandoPreview.value = false;
  }
}

function abrirDesdePreview() {
  const pedidoId = pedidoPreview.value.id;
  pedidoPreview.value = null;
  reanudar(pedidoId);
}

function volverALista() {
  pedidos.cerrarPedidoActivo();
  pedidos.cargarPedidosAbiertos(auth.sedeActualId);
}

function tocarProducto(producto) {
  if (areaUnica.value) {
    agregarConArea(producto, areaUnica.value.id);
    return;
  }

  productoParaArea.value = producto;
}

async function agregarConArea(producto, areaId) {
  if (accionEnCurso.value) return;
  accionEnCurso.value = true;

  try {
    await pedidos.agregarItem({ productoId: producto.id, areaPreparacionId: areaId, cantidad: 1 });
  } catch {
    error.value = 'No se pudo agregar el producto.';
  } finally {
    productoParaArea.value = null;
    accionEnCurso.value = false;
  }
}

async function quitar(itemId) {
  try {
    await pedidos.quitarItem(itemId);
  } catch {
    error.value = 'No se pudo quitar ese ítem (¿ya se envió a cocina?).';
  }
}

// "-" en un ítem con cantidad 1 lo quita directamente, en vez de rechazar
// una cantidad de 0 (el backend exige mínimo 1 — ver ItemPedido::actualizarCantidad()).
async function cambiarCantidad(item, delta) {
  const nuevaCantidad = item.cantidad + delta;

  if (nuevaCantidad < 1) {
    await quitar(item.id);
    return;
  }

  try {
    await pedidos.actualizarCantidadItem(item.id, nuevaCantidad);
  } catch {
    error.value = 'No se pudo actualizar la cantidad (¿ya se envió a cocina?).';
  }
}

// prompt() nativo a propósito, mismo criterio que confirm() ya usado en
// anularPedido()/eliminarSubCuenta() — una nota es texto corto y ocasional,
// no amerita un modal propio.
async function agregarNota(item) {
  const nota = window.prompt('Nota para este ítem (ej. "sin azúcar")', item.notas ?? '');
  if (nota === null) return; // canceló

  try {
    await pedidos.actualizarNotasItem(item.id, nota.trim() === '' ? null : nota.trim());
  } catch {
    error.value = 'No se pudo guardar la nota (¿ya se envió a cocina?).';
  }
}

async function enviarComanda() {
  if (accionEnCurso.value) return;
  accionEnCurso.value = true;

  try {
    await pedidos.enviarComanda();
  } catch {
    error.value = 'No se pudo enviar la comanda a cocina.';
  } finally {
    accionEnCurso.value = false;
  }
}

async function confirmarPago({ medio, monto }) {
  const grupoId = grupoDeMesaActiva.value?.id;

  try {
    await pedidos.pagar({ medio, monto });

    if (pedidos.pedidoActivo.estado === 'cobrado') {
      mostrarPago.value = false;
    }

    if (grupoId) await grupos.cargarGrupos(auth.sedeActualId); // refresca saldos de las mesas hermanas
  } catch {
    error.value = 'No se pudo registrar el pago.';
  }
}

async function anularPedido() {
  if (!confirm('¿Anular este pedido? No se puede deshacer.')) return;

  await pedidos.anular();
  volverALista();
}

</script>

<template>
  <div class="min-h-screen bg-bg pb-24">
    <p v-if="error" class="mx-4 mt-4 rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger" role="alert">
      {{ error }}
      <button class="ml-2 underline" @click="error = null">cerrar</button>
    </p>

    <p v-if="cargando" class="p-6 text-center text-text-muted">Cargando…</p>

    <!-- Sin pedido activo: lista + iniciar uno nuevo -->
    <div v-else-if="!pedidos.pedidoActivo" class="p-4">
      <div v-if="grupos.grupos.length > 0" class="mb-6">
        <div class="mb-3 flex items-center gap-2">
          <svg class="h-4 w-4 text-primary" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
            <path d="M7 13a4 4 0 0 1 0-6h2M13 7a4 4 0 0 1 0 6h-2" />
            <line x1="7" y1="10" x2="13" y2="10" />
          </svg>
          <h2 class="text-lg font-semibold text-text">Mesas unidas activas</h2>
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <button
            v-for="grupo in grupos.grupos"
            :key="grupo.id"
            class="overflow-hidden rounded-2xl border border-primary/20 bg-surface text-left shadow"
            @click="router.push({ name: 'grupo-mesa', params: { id: grupo.id } })"
          >
            <div class="flex flex-wrap gap-1 bg-primary/10 px-4 py-2">
              <span v-for="mesa in grupo.mesas" :key="mesa.id" class="rounded-full bg-surface px-2 py-0.5 text-xs font-medium text-text shadow-sm">
                {{ mesa.nombre }}
              </span>
            </div>
            <div class="px-4 py-3">
              <p class="text-lg font-semibold text-text">${{ grupo.total_combinado }}</p>
              <p class="text-xs text-text-muted">
                Total combinado
                <span v-if="grupo.modo === 'general'"> · pedido general</span>
              </p>
            </div>
          </button>
        </div>
      </div>

      <div class="mb-3 flex items-center justify-between gap-3">
        <h2 class="text-lg font-semibold text-text">Pedidos abiertos</h2>
        <input
          v-if="pedidos.pedidosAbiertos.length > 4"
          v-model="busquedaPedido"
          type="search"
          placeholder="Buscar mesa o #pedido…"
          class="w-48 rounded-xl border border-border bg-surface px-3 py-2 text-sm text-text"
        >
      </div>

      <!-- Tabs por tipo: con varios pedidos mezclados (mesa/mostrador/para
           llevar/domicilio) es más rápido ubicar uno filtrando por tipo que
           recorriendo una sola grilla — ver docs/DECISIONES.md. -->
      <div v-if="pedidos.pedidosAbiertos.length > 0" class="mb-3 flex gap-2 overflow-x-auto">
        <button
          class="shrink-0 rounded-xl px-3 py-1.5 text-sm font-medium"
          :class="tipoFiltro === 'todos' ? 'bg-primary text-white' : 'bg-surface text-text-muted shadow'"
          @click="tipoFiltro = 'todos'"
        >
          Todos ({{ pedidos.pedidosAbiertos.length }})
        </button>
        <button
          v-for="[valor, etiqueta] in TIPOS_PEDIDO"
          v-show="conteoPorTipo[valor]"
          :key="valor"
          class="shrink-0 rounded-xl px-3 py-1.5 text-sm font-medium"
          :class="tipoFiltro === valor ? 'bg-primary text-white' : 'bg-surface text-text-muted shadow'"
          @click="tipoFiltro = valor"
        >
          {{ etiqueta }} ({{ conteoPorTipo[valor] }})
        </button>
      </div>

      <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
        <div v-for="p in pedidosAbiertosFiltrados" :key="p.id" class="relative">
          <!-- Vista previa: ícono aparte del botón principal (dos <button>
               anidados no es HTML válido) — @click.stop para que tocar el
               ojo no abra también el pedido completo. -->
          <button
            class="absolute right-1.5 top-1.5 z-10 flex h-6 w-6 items-center justify-center rounded-full bg-surface-soft text-text-muted active:bg-border"
            aria-label="Vista previa del pedido"
            @click.stop="previsualizarPedido(p.id)"
          >
            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M1.5 10S4.5 4.5 10 4.5 18.5 10 18.5 10 15.5 15.5 10 15.5 1.5 10 1.5 10Z" stroke-linejoin="round" />
              <circle cx="10" cy="10" r="2.25" />
            </svg>
          </button>

          <button
            class="w-full rounded-2xl bg-surface p-3 text-left shadow active:bg-surface-soft"
            @click="reanudar(p.id)"
          >
            <!-- Encabezado corto a propósito: a 6 columnas una tarjeta
                 angosta no tiene espacio para "PARA LLEVAR · #77" y "hace
                 6h 33min" uno al lado del otro — el tipo ya lo dice la línea
                 de abajo (o los tabs de arriba, si están filtrando por uno). -->
            <div class="mb-1 flex items-center justify-between gap-1 pr-6">
              <span class="text-xs font-medium text-text-muted">#{{ p.id }}</span>
              <span
                class="shrink-0 text-xs"
                :class="esPedidoDemorado(p.created_at) ? 'font-semibold text-warning' : 'text-text-muted'"
              >
                {{ tiempoTranscurrido(p.created_at) }}
              </span>
            </div>
            <p class="truncate font-semibold text-text">
              {{ p.mesa ? p.mesa.nombre : etiquetaTipo(p.tipo) }}
              <span v-if="p.mesa?.grupo_mesa_id" class="text-xs font-normal text-text-muted">· unida</span>
            </p>
            <p class="text-sm text-text-muted">${{ p.total }}</p>
          </button>
        </div>
        <p v-if="pedidos.pedidosAbiertos.length === 0" class="col-span-full text-text-muted">
          No hay pedidos abiertos.
        </p>
        <p v-else-if="pedidosAbiertosFiltrados.length === 0" class="col-span-full text-text-muted">
          Ningún pedido coincide con este filtro.
        </p>
      </div>

      <button
        class="mb-6 w-full rounded-2xl border-2 border-dashed border-border py-4 font-medium text-text-muted active:bg-surface-soft disabled:opacity-50"
        :disabled="mesasDisponiblesParaUnir.length < 2"
        @click="abrirModalUnirMesas"
      >
        Unir mesas
      </button>

      <h2 class="mb-3 text-lg font-semibold text-text">Nuevo pedido</h2>
      <div v-if="!tipoNuevo" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <button
          v-for="opcion in [['mesa', 'Mesa'], ['mostrador', 'Mostrador'], ['para_llevar', 'Para llevar'], ['domicilio', 'Domicilio']]"
          :key="opcion[0]"
          class="rounded-2xl bg-surface p-6 text-lg font-medium text-text shadow active:bg-surface-soft"
          @click="elegirTipoNuevo(opcion[0])"
        >
          {{ opcion[1] }}
        </button>
      </div>

      <div v-else-if="tipoNuevo === 'mesa'" class="grid grid-cols-3 gap-3 sm:grid-cols-5">
        <button
          v-for="mesa in pedidos.mesas"
          :key="mesa.id"
          class="rounded-2xl bg-surface p-6 text-lg font-medium text-text shadow active:bg-surface-soft"
          @click="empezarPedido(mesa.id)"
        >
          {{ mesa.nombre }}
          <span v-if="mesa.grupo_mesa_id" class="block text-xs font-normal text-text-muted">unida</span>
        </button>
        <button class="rounded-2xl border-2 border-dashed border-border p-6 text-text-muted" @click="tipoNuevo = null">
          Cancelar
        </button>
      </div>
    </div>

    <!-- Pedido activo: carrito fijo + catálogo con tabs de categoría -->
    <div v-else class="p-4">
      <button
        class="mb-4 flex items-center gap-1 rounded-xl border border-border bg-surface px-4 py-2 text-sm font-medium text-text shadow active:bg-surface-soft"
        @click="volverALista"
      >
        ← Volver a pedidos
      </button>

      <!-- Mesas unidas: dentro del propio carrito, no en otra pantalla —
           pedido del usuario ("al ingresar a mesa debo poder unir mesa con
           +" y "hacer los pedidos para esa mesa unida y no mesa por mesa").
           Cada mesa sigue con su propio pedido/factura (ver DEC-025) — este
           selector solo evita tener que salir de aquí para cambiar de mesa
           dentro del mismo grupo. -->
      <div v-if="pedidos.pedidoActivo.tipo === 'mesa'" class="mb-4">
        <div v-if="grupoDeMesaActiva" class="rounded-2xl border border-primary/20 bg-surface p-3">
          <div class="mb-2 flex items-center justify-between gap-2">
            <p class="flex items-center gap-1.5 text-sm font-semibold text-text">
              <svg class="h-4 w-4 text-primary" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                <path d="M7 13a4 4 0 0 1 0-6h2M13 7a4 4 0 0 1 0 6h-2" />
                <line x1="7" y1="10" x2="13" y2="10" />
              </svg>
              Mesas unidas
            </p>
            <button
              class="text-xs font-medium text-text-muted underline"
              @click="router.push({ name: 'grupo-mesa', params: { id: grupoDeMesaActiva.id } })"
            >
              Ver grupo completo
            </button>
          </div>
          <!-- Modo General: un solo pedido para todo el grupo — no hay
               "otro carrito" al que saltar, así que las mesas se muestran
               solo como referencia (quiénes comparten esta cuenta), no como
               botones para cambiar de pedido. -->
          <p v-if="grupoDeMesaActiva.modo === 'general'" class="mb-2 text-xs text-text-muted">
            Pedido general — comparten esta cuenta:
          </p>
          <div class="flex flex-wrap gap-2">
            <template v-if="grupoDeMesaActiva.modo === 'general'">
              <span
                v-for="mesa in grupoDeMesaActiva.mesas"
                :key="mesa.id"
                class="rounded-lg bg-surface-soft px-3 py-1.5 text-sm font-medium text-text shadow-sm"
              >
                {{ mesa.nombre }}
              </span>
            </template>
            <template v-else>
              <button
                v-for="mesa in grupoDeMesaActiva.mesas"
                :key="mesa.id"
                class="rounded-lg px-3 py-1.5 text-sm font-medium"
                :class="mesa.id === pedidos.pedidoActivo.mesa.id ? 'bg-primary text-white' : 'bg-surface-soft text-text shadow-sm'"
                @click="irAMesaDelGrupo(mesa.id)"
              >
                {{ mesa.nombre }}
                <span v-if="!grupoDeMesaActiva.pedidos.some((p) => p.mesa_id === mesa.id)" class="opacity-70">(nuevo)</span>
              </button>
            </template>
            <button
              v-if="pedidos.pedidoActivo.estado === 'abierto'"
              class="rounded-lg border-2 border-dashed border-border px-3 py-1.5 text-sm font-medium text-text-muted"
              @click="abrirModalUnirDesdeMesaActiva"
            >
              + Unir mesa
            </button>
          </div>
        </div>
        <button
          v-else-if="pedidos.pedidoActivo.estado === 'abierto'"
          class="rounded-xl border-2 border-dashed border-border px-3 py-2 text-sm font-medium text-text-muted"
          @click="abrirModalUnirDesdeMesaActiva"
        >
          + Unir con otra mesa
        </button>
      </div>

      <div class="flex flex-col gap-4 md:flex-row md:items-start">
        <!-- Carrito: en escritorio queda fijo mientras se recorre el catálogo -->
        <aside class="rounded-2xl bg-surface p-4 shadow md:sticky md:top-4 md:w-80 md:shrink-0">
          <p class="font-semibold text-text">
            {{ pedidos.pedidoActivo.mesa ? pedidos.pedidoActivo.mesa.nombre : pedidos.pedidoActivo.tipo }}
            · {{ pedidos.pedidoActivo.estado }}
          </p>

          <ul class="my-3 divide-y divide-border">
            <li v-for="item in pedidos.pedidoActivo.items" :key="item.id" class="flex items-center justify-between py-2">
              <div>
                <p v-if="item.enviado" class="text-text">{{ item.cantidad }}× {{ item.nombre_producto }}</p>
                <template v-else>
                  <p class="text-text">{{ item.nombre_producto }}</p>
                  <div class="mt-1 flex items-center gap-2">
                    <button
                      class="flex h-6 w-6 items-center justify-center rounded-full border border-border text-text-muted"
                      aria-label="Quitar una unidad"
                      @click="cambiarCantidad(item, -1)"
                    >
                      -
                    </button>
                    <span class="w-4 text-center text-sm text-text">{{ item.cantidad }}</span>
                    <button
                      class="flex h-6 w-6 items-center justify-center rounded-full border border-border text-text-muted"
                      aria-label="Agregar una unidad"
                      @click="cambiarCantidad(item, 1)"
                    >
                      +
                    </button>
                  </div>
                  <button class="mt-1 text-xs text-text-muted underline" @click="agregarNota(item)">
                    {{ item.notas ? `📝 ${item.notas}` : '+ Nota' }}
                  </button>
                </template>
                <p v-if="item.enviado && item.notas" class="text-xs text-text-muted">📝 {{ item.notas }}</p>
                <p v-if="item.enviado" class="text-xs text-success">enviado a cocina</p>
              </div>
              <div class="flex items-center gap-3">
                <span class="text-text">${{ item.subtotal }}</span>
                <button
                  v-if="!item.enviado"
                  class="text-danger"
                  aria-label="Quitar ítem"
                  @click="quitar(item.id)"
                >
                  ✕
                </button>
              </div>
            </li>
          </ul>

          <p class="text-right text-lg font-semibold text-text">Total: ${{ pedidos.pedidoActivo.total }}</p>
          <p v-if="pedidos.pedidoActivo.total_pagado !== '0.00'" class="text-right text-sm text-text-muted">
            Pagado: ${{ pedidos.pedidoActivo.total_pagado }} · Pendiente: ${{ pedidos.pedidoActivo.saldo_pendiente }}
          </p>
        </aside>

        <!-- Catálogo: tabs de categoría, o resultados de búsqueda por nombre -->
        <section v-if="pedidos.pedidoActivo.estado === 'abierto'" class="min-w-0 flex-1">
          <div class="sticky top-4 z-10 mb-3 space-y-2 bg-bg py-2">
            <input
              v-model="busquedaProducto"
              type="search"
              placeholder="Buscar producto…"
              class="w-full rounded-xl border border-border bg-surface px-4 py-2 text-sm text-text shadow-sm"
            >

            <div v-if="!busquedaProducto" class="flex gap-2 overflow-x-auto">
              <button
                v-for="categoria in pedidos.categorias"
                :key="categoria.id"
                class="shrink-0 rounded-xl px-4 py-2 text-sm font-medium"
                :class="categoriaActivaId === categoria.id ? 'bg-primary text-white' : 'bg-surface text-text-muted shadow'"
                @click="categoriaActivaId = categoria.id"
              >
                {{ categoria.nombre }}
              </button>
            </div>
          </div>

          <!-- 6 columnas en pantallas anchas (tablet/escritorio del POS),
               escalando hacia abajo en pantallas angostas — con textos un
               poco más grandes que antes, en la misma proporción entre sí
               (nombre > descripción, precio destacado). -->
          <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
            <button
              v-for="producto in productosAMostrar"
              :key="producto.id"
              class="flex flex-col overflow-hidden rounded-xl bg-surface text-left shadow active:bg-surface-soft"
              @click="tocarProducto(producto)"
            >
              <div class="aspect-square w-full bg-surface-soft">
                <img
                  v-if="producto.imagen_url"
                  :src="producto.imagen_url"
                  :alt="producto.nombre"
                  class="h-full w-full object-cover"
                  loading="lazy"
                >
                <div v-else class="flex h-full w-full items-center justify-center text-4xl font-semibold text-text-muted">
                  {{ producto.nombre.charAt(0).toUpperCase() }}
                </div>
              </div>
              <div class="flex flex-1 flex-col gap-1 p-3">
                <p class="text-base font-medium leading-tight text-text">{{ producto.nombre }}</p>
                <p v-if="producto.descripcion" class="line-clamp-2 text-sm text-text-muted">{{ producto.descripcion }}</p>
                <p class="mt-auto pt-1 text-lg font-semibold text-primary">${{ producto.precio }}</p>
              </div>
            </button>
            <p v-if="busquedaProducto && productosAMostrar.length === 0" class="col-span-full text-text-muted">
              Ningún producto coincide con "{{ busquedaProducto }}".
            </p>
          </div>
        </section>
      </div>

      <!-- Barra de acciones fija -->
      <div class="fixed inset-x-0 bottom-0 flex gap-2 bg-surface p-3 shadow-[0_-2px_8px_rgba(0,0,0,0.1)]">
        <button
          v-if="pedidos.pedidoActivo.estado === 'abierto' && hayItemsPendientes"
          class="flex-1 rounded-xl bg-warning py-4 font-semibold text-white"
          @click="enviarComanda"
        >
          Enviar a cocina
        </button>
        <button
          v-if="pedidos.pedidoActivo.estado === 'abierto' && auth.puedeVerCaja"
          class="flex-1 rounded-xl bg-success py-4 font-semibold text-white"
          @click="mostrarPago = true"
        >
          Cobrar
        </button>
        <button
          v-if="pedidos.pedidoActivo.estado === 'abierto' && pedidos.pedidoActivo.items.length > 0"
          class="rounded-xl border border-border px-4 py-4 text-text"
          @click="mostrarDividirCuenta = true"
        >
          Dividir
        </button>
        <button
          v-if="pedidos.pedidoActivo.estado === 'abierto'"
          class="rounded-xl border border-danger px-4 py-4 text-danger"
          @click="anularPedido"
        >
          Anular
        </button>
      </div>
    </div>

    <!-- Vista previa de un pedido de la lista, sin entrar a su carrito -->
    <div v-if="cargandoPreview || pedidoPreview" class="fixed inset-0 z-20 flex items-center justify-center bg-black/40">
      <div class="w-full max-w-sm rounded-2xl bg-surface p-6">
        <p v-if="cargandoPreview" class="py-6 text-center text-text-muted">Cargando…</p>

        <template v-else-if="pedidoPreview">
          <div class="mb-3 flex items-start justify-between gap-2">
            <div>
              <p class="text-xs font-medium text-text-muted">#{{ pedidoPreview.id }}</p>
              <h2 class="text-lg font-semibold text-text">
                {{ pedidoPreview.mesa ? pedidoPreview.mesa.nombre : etiquetaTipo(pedidoPreview.tipo) }}
              </h2>
            </div>
            <button class="text-text-muted" aria-label="Cerrar" @click="pedidoPreview = null">✕</button>
          </div>

          <ul class="mb-4 max-h-64 divide-y divide-border overflow-y-auto">
            <li v-for="item in pedidoPreview.items" :key="item.id" class="flex items-center justify-between gap-2 py-2 text-sm">
              <span class="text-text">{{ item.cantidad }}× {{ item.nombre_producto }}</span>
              <span class="shrink-0 text-text-muted">${{ item.subtotal }}</span>
            </li>
            <p v-if="pedidoPreview.items.length === 0" class="py-2 text-sm text-text-muted">
              Todavía no tiene ítems.
            </p>
          </ul>

          <p class="mb-4 flex items-center justify-between text-text">
            <span class="text-sm text-text-muted">Total</span>
            <span class="text-lg font-semibold">${{ pedidoPreview.total }}</span>
          </p>

          <div class="flex gap-2">
            <button class="flex-1 rounded-xl bg-primary py-3 font-medium text-white" @click="abrirDesdePreview">
              Abrir pedido
            </button>
            <button class="rounded-xl border border-border px-4 py-3 text-text-muted" @click="pedidoPreview = null">
              Cerrar
            </button>
          </div>
        </template>
      </div>
    </div>

    <!-- Elegir área cuando hay más de una -->
    <div v-if="productoParaArea" class="fixed inset-0 z-20 flex items-center justify-center bg-black/40">
      <div class="w-full max-w-sm rounded-2xl bg-surface p-6">
        <h2 class="mb-4 text-lg font-semibold text-text">¿A qué área va {{ productoParaArea.nombre }}?</h2>
        <div class="grid grid-cols-2 gap-3">
          <button
            v-for="area in pedidos.areas"
            :key="area.id"
            class="rounded-xl bg-surface-soft py-4 font-medium text-text"
            @click="agregarConArea(productoParaArea, area.id)"
          >
            {{ area.nombre }}
          </button>
        </div>
        <button class="mt-4 w-full text-text-muted" @click="productoParaArea = null">Cancelar</button>
      </div>
    </div>

    <PagoModal
      v-if="mostrarPago"
      :saldo-pendiente="pedidos.pedidoActivo.saldo_pendiente"
      :items="itemsParaCobro"
      :permite-cliente="auth.puedeVerCaja"
      :cliente-actual="pedidos.pedidoActivo.cliente"
      :on-buscar-clientes="(termino) => pedidos.buscarClientes(auth.sedeActualId, termino)"
      :on-crear-cliente="(datos) => pedidos.crearCliente(auth.sedeActualId, datos)"
      :on-asignar-cliente="pedidos.asignarCliente"
      @cerrar="mostrarPago = false"
      @confirmar="confirmarPago"
    />

    <!-- Dividir cuenta (Fase 10) -->
    <DividirCuentaModal
      v-if="mostrarDividirCuenta"
      :pedido="pedidos.pedidoActivo"
      :sub-cuentas="subCuentas.lista"
      :puede-cobrar="auth.puedeVerCaja"
      :on-crear="crearSubCuenta"
      :on-eliminar="eliminarSubCuenta"
      :on-pagar="pagarSubCuenta"
      @cerrar="mostrarDividirCuenta = false"
    />

    <!-- Unir mesas (Fase 9) / sumar mesa a un grupo existente -->
    <div v-if="mostrarUnirMesas" class="fixed inset-0 z-20 flex items-center justify-center bg-black/40">
      <div class="w-full max-w-sm rounded-2xl bg-surface p-6">
        <h2 class="mb-4 text-lg font-semibold text-text">{{ tituloModalUnion }}</h2>
        <div class="mb-4 grid grid-cols-3 gap-2">
          <button
            v-for="mesa in mesasDisponiblesParaUnir"
            :key="mesa.id"
            class="rounded-xl border-2 p-3 text-sm font-medium"
            :class="mesasParaUnir.includes(mesa.id) ? 'border-success bg-success-soft text-success' : 'border-border text-text'"
            @click="alternarMesaParaUnir(mesa.id)"
          >
            {{ mesa.nombre }}
          </button>
          <p v-if="mesasDisponiblesParaUnir.length === 0" class="col-span-3 text-sm text-text-muted">
            No hay mesas libres para unir (¿ya están todas en un grupo?).
          </p>
        </div>

        <!-- El modo se fija al crear el grupo y ya no cambia (ver
             docs/DECISIONES.md) — por eso solo se muestra al armar un grupo
             nuevo, nunca al sumar una mesa a uno que ya existe. -->
        <div v-if="mostrarSelectorModo" class="mb-4">
          <label class="mb-2 block text-sm font-medium text-text-muted">¿Cómo se cobra?</label>
          <div class="grid grid-cols-2 gap-2">
            <button
              type="button"
              class="rounded-xl border-2 p-3 text-left text-sm"
              :class="modoParaUnir === 'independiente' ? 'border-primary bg-primary/10' : 'border-border'"
              @click="modoParaUnir = 'independiente'"
            >
              <span class="block font-medium text-text">Independiente</span>
              <span class="text-xs text-text-muted">Cada mesa su cuenta</span>
            </button>
            <button
              type="button"
              class="rounded-xl border-2 p-3 text-left text-sm"
              :class="modoParaUnir === 'general' ? 'border-primary bg-primary/10' : 'border-border'"
              @click="modoParaUnir = 'general'"
            >
              <span class="block font-medium text-text">General</span>
              <span class="text-xs text-text-muted">Una sola cuenta para todas</span>
            </button>
          </div>
        </div>

        <p v-if="errorUnion" class="mb-3 text-sm text-danger" role="alert">{{ errorUnion }}</p>
        <div class="flex gap-2">
          <button
            class="flex-1 rounded-xl bg-success py-3 font-medium text-white disabled:opacity-50"
            :disabled="mesasParaUnir.length < minimoMesasParaConfirmar || accionEnCurso"
            @click="confirmarUnion"
          >
            {{ etiquetaBotonUnion }}
          </button>
          <button class="rounded-xl border border-border px-4 py-3 text-text-muted" @click="mostrarUnirMesas = false">
            Cancelar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
