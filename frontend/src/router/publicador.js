import Dashboard from "../Pages/Publicador/dashboard.vue";
import Pagina1 from "../Pages/Publicador/pagina1.vue";

const publicadorRoutes = [
  {
    path: "/publicador",
    children: [
       { path: "", name: "publicador.dashboard", component: Dashboard },
      { path: "pagina1", name: "publicador.pagina1", component: Pagina1 },
    ],
  },
];

export default publicadorRoutes;