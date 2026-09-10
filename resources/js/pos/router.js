import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from './stores/auth';

// meta.permiso referencia un getter `puedeVer<Permiso>` del auth store — ver
// stores/auth.js. Sin esto, cualquier rol operativo autenticado podía
// navegar directo a cualquier pantalla (ej. un mesero a /pos/caja) aunque el
// ícono estuviera oculto en el sidebar; el backend siempre fue la
// autorización real, esto es solo para no exponer pantallas que el rol no
// puede usar.
const routes = [
  { path: '/pos/login', name: 'login', component: () => import('./views/LoginView.vue') },
  { path: '/pos/sede', name: 'sede', component: () => import('./views/SedeSelectView.vue'), meta: { requiereAuth: true } },
  { path: '/pos/inicio', name: 'inicio', component: () => import('./views/InicioView.vue'), meta: { requiereAuth: true, requiereSede: true } },
  { path: '/pos', name: 'pos', component: () => import('./views/PosView.vue'), meta: { requiereAuth: true, requiereSede: true, permiso: 'Mostrador' } },
  { path: '/pos/kds', name: 'kds', component: () => import('./views/KdsView.vue'), meta: { requiereAuth: true, requiereSede: true, permiso: 'Cocina' } },
  { path: '/pos/grupo/:id', name: 'grupo-mesa', component: () => import('./views/GrupoMesaView.vue'), meta: { requiereAuth: true, requiereSede: true, permiso: 'Mostrador' } },
  { path: '/pos/caja', name: 'caja', component: () => import('./views/CajaView.vue'), meta: { requiereAuth: true, requiereSede: true, permiso: 'Caja' } },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach(async (to) => {
  const auth = useAuthStore();

  if (auth.usuario === null && to.meta.requiereAuth) {
    await auth.recuperarSesion();
  }

  if (to.meta.requiereAuth && !auth.autenticado) {
    return { name: 'login' };
  }

  if (to.name === 'login' && auth.autenticado) {
    return { name: 'pos' };
  }

  if (to.meta.requiereSede && !auth.sedeActualId) {
    return { name: 'sede' };
  }

  if (to.meta.permiso && !auth[`puedeVer${to.meta.permiso}`]) {
    return auth.rutaInicioSegunRol;
  }

  return true;
});

export default router;
