import { createRouter, createWebHistory } from "vue-router";
import publicadorRoutes from "./publicador";
import consumidorRoutes from "./consumidor";
import adminRoutes from "./administrador";



const routes = [
  {
    path: '/',
    redirect: '/admin/dashboard',
  },
  ...publicadorRoutes,
  ...consumidorRoutes,
  ...adminRoutes,
  {
    path: '/:pathMatch(.*)*',
    redirect: '/admin/dashboard',
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

export default router;
