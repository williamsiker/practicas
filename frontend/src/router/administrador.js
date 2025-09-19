import Dashboard from "../Pages/Admin/dashboard.vue";
import Tarifas from "../Pages/Admin/tarifas.vue";

const adminRoutes = [
  {
    path: "/admin",
    children: [
      { path: "", name: "home", component: Dashboard },
      { path: "dashboard", name: "admin.dashboard", component: Dashboard },
      { path: "tarifas", name: "admin.tarifas", component: Tarifas },
    ],
  },
];

export default adminRoutes;