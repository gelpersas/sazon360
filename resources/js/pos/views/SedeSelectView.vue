<script setup>
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();

function elegir(sedeId) {
  auth.elegirSede(sedeId);
  router.push({ name: 'pos' });
}
</script>

<template>
  <div class="min-h-screen bg-bg p-4">
    <h1 class="mb-6 mt-8 text-center text-2xl font-semibold text-text">¿En qué sede trabajas?</h1>

    <div class="mx-auto flex max-w-md flex-col gap-3">
      <button
        v-for="sede in auth.sedes"
        :key="sede.id"
        class="rounded-2xl bg-surface px-6 py-6 text-left text-xl font-medium text-text shadow active:bg-surface-soft"
        @click="elegir(sede.id)"
      >
        {{ sede.nombre }}
      </button>

      <p v-if="auth.sedes.length === 0" class="text-center text-text-muted">
        Tu usuario no tiene acceso a ninguna sede todavía.
      </p>
    </div>
  </div>
</template>
