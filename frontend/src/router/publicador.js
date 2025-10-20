import Dashboard from "../Pages/Publicador/dashboard.vue";
import Servicios from "../Pages/Publicador/servicios.vue";

const publicadorRoutes = [
  {
    path: "/publicador",
    children: [
       { path: "", name: "publicador.dashboard", component: Dashboard },
      { path: "servicios", name: "publicador.servicios", component: Servicios },
    ],
  },
];

export default publicadorRoutes;
