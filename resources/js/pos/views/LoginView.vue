<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();

const email = ref('');
const password = ref('');
const enviando = ref(false);

async function enviar() {
  if (enviando.value) return; // prevención de doble toque

  enviando.value = true;

  try {
    await auth.iniciarSesion(email.value, password.value);
    router.push(auth.sedeActualId ? { name: 'pos' } : { name: 'sede' });
  } catch {
    // el mensaje ya queda en auth.error
  } finally {
    enviando.value = false;
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-bg p-4">
    <form
      class="w-full max-w-sm rounded-2xl bg-surface p-8 shadow-lg"
      @submit.prevent="enviar"
    >
      <h1 class="mb-6 text-center text-2xl font-semibold text-text">Sazón360 POS</h1>

      <label class="mb-1 block text-sm font-medium text-text-muted">Correo</label>
      <input
        v-model="email"
        type="email"
        required
        autocomplete="username"
        class="mb-4 w-full rounded-xl border border-border bg-surface px-4 py-3 text-lg text-text"
      >

      <label class="mb-1 block text-sm font-medium text-text-muted">Contraseña</label>
      <input
        v-model="password"
        type="password"
        required
        autocomplete="current-password"
        class="mb-4 w-full rounded-xl border border-border bg-surface px-4 py-3 text-lg text-text"
      >

      <p
        v-if="auth.error"
        class="mb-4 rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger"
        role="alert"
      >
        {{ auth.error }}
      </p>

      <button
        type="submit"
        :disabled="enviando"
        class="w-full rounded-xl bg-primary py-4 text-lg font-semibold text-white active:bg-primary-hover disabled:opacity-50"
      >
        {{ enviando ? 'Entrando…' : 'Entrar' }}
      </button>
    </form>
  </div>
</template>
