import Dashboard from "../Pages/Admin/dashboard.vue";
import Tarifas from "../Pages/Admin/tarifas.vue";
import Aprobaciones from "../Pages/Admin/aprobaciones.vue";

const adminRoutes = [
  {
    path: "/admin",
    children: [
      { path: "", name: "home", component: Dashboard },
      { path: "dashboard", name: "admin.dashboard", component: Dashboard },
      { path: "tarifas", name: "admin.tarifas", component: Tarifas },
      { path: "servicios", name: "admin.servicios", component: Aprobaciones },
    ],
  },
];

export default adminRoutes;
