import '../../css/pos.css';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import { useTemaStore } from './stores/tema';

const app = createApp(App);

app.use(createPinia());
app.use(router);

// Antes de montar: aplica el tema guardado (localStorage) para que la
// primera pintura ya sea la correcta, sin parpadeo — ver stores/tema.js.
useTemaStore().inicializar();

app.mount('#pos-app');
