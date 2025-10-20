import Dashboard from "../Pages/Consumidor/dashboard.vue";
import Catalogo from "../Pages/Consumidor/catalogo.vue";
import MisServicios from "../Pages/Consumidor/misServicios.vue";

const consumidorRoutes = [
  {
    path: "/consumidor",
    children: [
       { path: "", name: "consumidor.dashboard", component: Dashboard },
      { path: "catalogo", name: "consumidor.catalogo", component: Catalogo },
      { path: "mis-servicios", name: "consumidor.mis-servicios", component: MisServicios },
    ],
  },
];

export default consumidorRoutes;
