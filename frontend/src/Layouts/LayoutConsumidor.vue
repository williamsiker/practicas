<template>
  <a-layout class="dashboard">
    <!-- Sidebar -->
    <a-layout-sider
      v-model:collapsed="collapsed"
      collapsible
      breakpoint="md"
      theme="light"
      :width="240"
      :collapsed-width="60"
      :trigger="null"
      class="sider "
    >
      <!-- Logo -->
      <div class="sider-logo">
        <img
          src="https://inscripciones.admision.unap.edu.pe/build/assets/logotiny-e0fccd92.png"
          alt="Logo"
        />
        <span v-if="!collapsed" class="sider-title">Plataforma de servicios</span>
      </div>

      <!-- Usuario -->

      <div v-if="!collapsed" class="sider-user-pro">
        <div class="profile">
        <a-avatar src="https://i.pravatar.cc/100" :size="80" />
            <div class="username">Ariel - Consumidor</div>
        </div>

        <a-select
            v-model:value="proceso"
            show-search
            placeholder="Selecciona un proceso"
            style="width: 100%"
            size="middle"
            option-filter-prop="label"
            :options="procesos"
            @change="cambiarProceso"
        />
        </div>

      <!-- Menú -->
      <a-menu
        v-model:selectedKeys="selectedKeys"
        v-model:openKeys="openKeys"
        mode="inline"
        class="sider-menu"
        style="padding: 0px 5px;"
      >
        <template v-for="item in menuItems" :key="item.key">
          <a-menu-item v-if="!item.children" :key="item.key">
            <router-link :to="item.route" class="menu-link">
              <component :is="item.icon" class="menu-icon" />
              <span>{{ item.label }}</span>
            </router-link>
          </a-menu-item>

          <a-sub-menu v-else :key="item.key ">
            <template #icon>
              <component :is="item.icon" class="menu-icon" />
            </template>
            <template #title> <div style="margin-left: 10px;">{{ item.label }}</div> </template>
            <a-menu-item v-for="child in item.children" :key="child.key">
              <router-link :to="child.route" class="menu-link">
                <component :is="child.icon" class="menu-icon" />
                <span>{{ child.label }}</span>
              </router-link>
            </a-menu-item>
          </a-sub-menu>
        </template>
      </a-menu>
    </a-layout-sider>

    <!-- Main Layout -->
    <a-layout>
      <!-- Header -->
      <a-layout-header class="header">
        <menu-fold-outlined
          class="trigger"
          @click="collapsed = !collapsed"
        />
        <span class="header-title">{{ props.pagina }}</span>
        <div class="header-right">
          <a-avatar src="https://i.pravatar.cc/50" />
        </div>
      </a-layout-header>

      <!-- Content -->
      <a-layout-content class="content">
        <a-card class="content-card">
          <slot/>
        </a-card>
      </a-layout-content>
    </a-layout>
  </a-layout>
</template>

<script setup>
import { ref, watch } from "vue";
import { useRoute } from "vue-router";
import {
  AppstoreFilled,
  SettingFilled,
  MenuFoldOutlined,
  FileTextOutlined,
  EyeFilled,
  BulbOutlined,
} from "@ant-design/icons-vue";

const collapsed = ref(false);
const selectedKeys = ref([]);
const openKeys = ref([]);
const proceso = ref(1);

const route = useRoute();

const props = defineProps({
  pagina: {
    type: String,
    required: true
  }
})

const procesos = ref([
  { value: 1, label: "Periodo 2025-II" },
  { value: 2, label: "Periodo 2025-I" },
]);

const menuItems = [
  { key: "dashboard", icon: AppstoreFilled, label: "Dashboard", route: "/consumidor/dashboard" },
  {
    key: "catalogo-grupo",
    icon: EyeFilled,
    label: "Vistas",
    children: [
      { key: "catalogo", icon: FileTextOutlined, label: "Catalogo", route: "/consumidor/catalogo" },
      { key: "mis-servicios", icon: BulbOutlined, label: "Mis servicios", route: "/consumidor/mis-servicios" }
    ],
  },
  // {
  //   key: "reportes",
  //   icon: FileTextOutlined,
  //   label: "Reportes",
  //   children: [
  //     { key: "resumen", icon: FileTextOutlined, label: "Resumen", route: "/resumen" },
  //     { key: "ratio", icon: FileTextOutlined, label: "Ratio", route: "/ratio" },
  //   ],
  // },
];

watch(
  () => route?.fullPath,
  (newPath) => {
    if (!newPath) return;

    const activeItem = menuItems
      .flatMap((item) => (item.children ? item.children : [item]))
      .find((item) => item.route === newPath);

    selectedKeys.value = activeItem ? [activeItem.key] : [];

    const parent = menuItems.find((item) =>
      item.children?.some((c) => c.route === newPath)
    );
    openKeys.value = parent ? [parent.key] : [];
  },
  { immediate: true }
);

const cambiarProceso = () => {
  console.log("Cambiar proceso a:", proceso.value);
};
</script>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap");

.dashboard {
  min-height: 100vh;
  background: #f9fafb;
  font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  font-size: 14px;
  color: #374151;
}

.sider {
  background: rgba(255, 255, 255, 0.7) !important;
  background: #fafafa !important;
  backdrop-filter: blur(16px);
  border-right: 1px solid #e5e7eb;
  box-shadow: 2px 0 8px rgba(0, 0, 0, 0.05);
}

.sider-logo {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 18px 16px;
  height: 64px;
  border-bottom: 1px solid #f3f4f6;
}

.sider-logo img {
  height: 28px;
}

.sider-title {
  font-weight: 600;
  font-size: 14px;
  color: #111827;
}

.sider-user {
  padding: 16px;
  text-align: center;
  border-bottom: 1px solid #f3f4f6;
}

.username {
  margin: 10px 0 8px;
  font-weight: 500;
  font-size: 13px;
  color: #a7a8aa;
}

.sider-menu {
  background: transparent !important;
  border: none;
  padding: 8px 0;
}

.menu-link {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13px;
  width: 100%;
  color: #4b5563 !important;
  transition: all 0.2s ease;
}

.menu-icon {
  font-size: 14px;
  color: #9ca3af;
  transition: color 0.2s ease;
}


.sider-menu :deep(.ant-menu-item-selected) {
  background: #e0f2fe !important; 
background: #e6e8eb !important; 
  border-radius: 6px;
}

.sider-menu :deep(.ant-menu-item-selected .menu-link),
.sider-menu :deep(.ant-menu-item-selected .menu-icon) {
  /* color: #007aff !important; */
  color:black !important;
  font-weight: 500;
}

/* Submenú activo (plomo suave) */
.sider-menu :deep(.ant-menu-submenu-selected > .ant-menu-submenu-title) {
  /* background: #f0f0f0 !important; */
  border-radius: 6px;
  color: #007aff !important;
  color: #414141 !important;
  /* font-weight: 500; */
}

/* Hover en submenú */
.sider-menu :deep(.ant-menu-submenu-title:hover) {
  background: #e5e7eb !important;
  border-radius: 6px;
  color: #111827 !important;
}

/* Hover en ítem */
.menu-link:hover {
  color: #111827 !important;
}

.menu-link:hover .menu-icon {
  color: #374151;
}

/* Header */
.header {
  background: #ffffffe6;
  backdrop-filter: blur(12px);
  padding: 0 20px;
  display: flex;
  align-items: center;
  border-bottom: 1px solid #e5e7eb;
  height: 60px;
}

.trigger {
  font-size: 16px;
  cursor: pointer;
  color: #6b7280;
  padding: 6px;
  border-radius: 6px;
  transition: all 0.2s ease;
}

.trigger:hover {
  background-color: #f3f4f6;
  color: #007aff;
}

.header-title {
  margin-left: 12px;
  font-weight: 600;
  font-size: 15px;
  color: #111827;
}

.header-right {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 12px;
}

/* Content */
.content {
  margin: 8px;
}

.content-card {
  border-radius: 3px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
  background: #fff;
  border: 1px solid #e5e7eb;
  min-height: calc(100vh - 80px);
}

.content-card .ant-card-body {
  padding: 20px;
}

/* Responsivo */
@media (max-width: 768px) {
  .header {
    padding: 0 16px;
  }
  .content {
    margin: 16px;
  }
  .content-card .ant-card-body {
    padding: 16px;
  }
}


.sider-user-pro {
  padding: 18px 16px;
  border-bottom: 1px solid #f3f4f6;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.profile {
  text-align: center;
}

.username {
  margin-top: 8px;
  font-weight: 600;
  font-size: 14px;
  color: #111827;
}

/* Ajustar el select al estilo macOS */
.sider-user-pro :deep(.ant-select-selector) {
  border-radius: 10px !important;
  border: 1px solid #e5e7eb !important;
  background: rgba(255, 255, 255, 0.6) !important;
  backdrop-filter: blur(8px);
  box-shadow: 0 1px 3px rgba(0,0,0,0.06);
  transition: all 0.2s ease;
}

.sider-user-pro :deep(.ant-select-selector:hover) {
  border-color: #007aff !important;
}


</style>
