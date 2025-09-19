import { createRouter, createWebHistory } from "vue-router";
import publicadorRoutes from "./publicador";
import consumidorRoutes from "./consumidor";
import adminRoutes from "./administrador";



const routes = [
    ...publicadorRoutes,
    ...consumidorRoutes,
    ...adminRoutes
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

export default router;
