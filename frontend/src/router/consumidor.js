import Dashboard from "../Pages/Consumidor/dashboard.vue";
import Pagina1 from "../Pages/Consumidor/pagina1.vue";

const consumidorRoutes = [
  {
    path: "/consumidor",
    children: [
       { path: "", name: "consumidor.dashboard", component: Dashboard },
      { path: "pagina1", name: "consumidor.pagina1", component: Pagina1 },
    ],
  },
];

export default consumidorRoutes;