<script setup>
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useTemaStore } from '../stores/tema';

// Sidebar de íconos persistente (Fase 12a, ver docs/DECISIONES.md DEC-033)
// — reemplaza la barra horizontal de texto de Fase 11a (DEC-027) por una
// columna angosta de íconos + etiqueta corta, inspirada en la segunda
// referencia visual del usuario. Login/SedeSelect no tienen
// meta.requiereSede, así que no muestran la sidebar.
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const tema = useTemaStore();

async function cerrarSesion() {
  await auth.cerrarSesion();
  router.push({ name: 'login' });
}

// Claro → Oscuro → Auto → Claro… un solo botón que cicla, no 3 aparte (poco
// espacio en la columna de 80px) — ver stores/tema.js.
const CICLO_TEMA = ['claro', 'oscuro', 'auto'];

function siguienteTema() {
  const indice = CICLO_TEMA.indexOf(tema.preferencia);
  tema.establecer(CICLO_TEMA[(indice + 1) % CICLO_TEMA.length]);
}
</script>

<template>
  <div class="flex h-screen">
    <aside
      v-if="route.meta.requiereSede"
      class="flex w-20 shrink-0 flex-col items-center border-r border-border bg-surface py-4"
    >
      <div class="mb-6 px-1 text-center">
        <p class="text-xs font-semibold leading-tight text-text">{{ auth.sedeActual?.nombre }}</p>
        <p class="text-[10px] leading-tight text-text-muted">{{ auth.usuario?.name }}</p>
      </div>

      <!-- justify-center (no flex-start): con pocos accesos (ej. un mesero,
           que hoy solo ve "Mostrador") los íconos quedaban pegados arriba
           con un hueco enorme vacío hasta "Salir" — centrarlos se ve
           equilibrado sin importar cuántos accesos tenga el rol. -->
      <nav class="flex flex-1 flex-col items-center justify-center gap-3">
        <router-link
          :to="{ name: 'inicio' }"
          class="flex w-16 flex-col items-center gap-1 rounded-xl py-2 text-[11px]"
          :class="route.name === 'inicio' ? 'bg-primary text-white' : 'text-text-muted'"
        >
          <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9.5 10 3l7 6.5" />
            <path d="M5 8v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V8" />
          </svg>
          Inicio
        </router-link>

        <router-link
          v-if="auth.puedeVerMostrador"
          :to="{ name: 'pos' }"
          class="flex w-16 flex-col items-center gap-1 rounded-xl py-2 text-[11px]"
          :class="route.name === 'pos' ? 'bg-primary text-white' : 'text-text-muted'"
        >
          <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5">
            <rect x="2" y="2" width="7" height="7" rx="1" />
            <rect x="11" y="2" width="7" height="7" rx="1" />
            <rect x="2" y="11" width="7" height="7" rx="1" />
            <rect x="11" y="11" width="7" height="7" rx="1" />
          </svg>
          Mostrador
        </router-link>

        <router-link
          v-if="auth.puedeVerCocina"
          :to="{ name: 'kds' }"
          class="flex w-16 flex-col items-center gap-1 rounded-xl py-2 text-[11px]"
          :class="route.name === 'kds' ? 'bg-primary text-white' : 'text-text-muted'"
        >
          <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5">
            <rect x="2" y="3" width="16" height="11" rx="1.5" />
            <line x1="7" y1="17" x2="13" y2="17" />
            <line x1="10" y1="14" x2="10" y2="17" />
          </svg>
          Cocina
        </router-link>

        <router-link
          v-if="auth.puedeVerCaja"
          :to="{ name: 'caja' }"
          class="flex w-16 flex-col items-center gap-1 rounded-xl py-2 text-[11px]"
          :class="route.name === 'caja' ? 'bg-primary text-white' : 'text-text-muted'"
        >
          <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5">
            <rect x="2" y="6" width="16" height="10" rx="1.5" />
            <circle cx="10" cy="11" r="2" />
            <line x1="4" y1="6" x2="4" y2="4" />
            <line x1="16" y1="6" x2="16" y2="4" />
            <line x1="4" y1="4" x2="16" y2="4" />
          </svg>
          Caja
        </router-link>
      </nav>

      <button
        class="flex w-16 flex-col items-center gap-1 rounded-xl py-2 text-[11px] text-text-muted"
        :title="`Tema: ${tema.preferencia}`"
        @click="siguienteTema"
      >
        <svg v-if="tema.preferencia === 'claro'" class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5">
          <circle cx="10" cy="10" r="3.5" />
          <path stroke-linecap="round" d="M10 2v2M10 16v2M18 10h-2M4 10H2M15.5 4.5l-1.4 1.4M5.9 14.1l-1.4 1.4M15.5 15.5l-1.4-1.4M5.9 5.9 4.5 4.5" />
        </svg>
        <svg v-else-if="tema.preferencia === 'oscuro'" class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round">
          <path d="M17 11.5A7 7 0 1 1 8.5 3a5.5 5.5 0 0 0 8.5 8.5Z" />
        </svg>
        <svg v-else class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5">
          <circle cx="10" cy="10" r="7" />
          <path d="M10 3a7 7 0 0 1 0 14z" fill="currentColor" stroke="none" />
        </svg>
        {{ tema.preferencia === 'claro' ? 'Claro' : tema.preferencia === 'oscuro' ? 'Oscuro' : 'Auto' }}
      </button>

      <button class="mt-1 flex w-16 flex-col items-center gap-1 rounded-xl py-2 text-[11px] text-text-muted" @click="cerrarSesion">
        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M8 3H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h4" />
          <line x1="14" y1="10" x2="6.5" y2="10" />
          <polyline points="11,6.5 14.5,10 11,13.5" />
        </svg>
        Salir
      </button>
    </aside>

    <div class="flex-1 overflow-y-auto">
      <router-view />
    </div>
  </div>
</template>
