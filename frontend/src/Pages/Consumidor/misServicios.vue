<template>
  <Layout :pagina="'Mis servicios'">
    <div class="page-header">
      <h2>Servicios que tengo activos</h2>
      <p>Revisa el estado, la vigencia y las acciones disponibles para cada servicio.</p>
    </div>

    <div class="overview">
      <a-card class="overview-card" bordered>
        <div class="overview-value">{{ summary.total }}</div>
        <div class="overview-label">Servicios totales</div>
      </a-card>
      <a-card class="overview-card" bordered>
        <div class="overview-value">{{ summary.activos }}</div>
        <div class="overview-label">Activos</div>
      </a-card>
      <a-card class="overview-card" bordered>
        <div class="overview-value">{{ summary.pendientes }}</div>
        <div class="overview-label">Pendientes</div>
      </a-card>
    </div>

    <a-table
      :columns="columns"
      :data-source="services"
      :pagination="{ pageSize: 6, showSizeChanger: false }"
      :row-key="record => record.id"
      :scroll="{ x: 768 }"
      class="services-table"
    >
      <template #bodyCell="{ column, record }">
        <template v-if="column.key === 'service'">
          <div class="service-cell">
            <div class="service-name">{{ record.name }}</div>
            <div class="service-meta">
              {{ record.organizer }} | Vigente hasta {{ formatDate(record.validUntil) }}
            </div>
          </div>
        </template>

        <template v-else-if="column.key === 'status'">
          <a-tag :color="statusColors[record.status]">{{ statusLabels[record.status] }}</a-tag>
        </template>

        <template v-else-if="column.key === 'level'">
          <a-tag color="blue">{{ record.level }}</a-tag>
        </template>

        <template v-else-if="column.key === 'updatedAt'">
          {{ formatDate(record.updatedAt) }}
        </template>

        <template v-else-if="column.key === 'actions'">
          <a-space size="small">
            <a-button type="link" size="small">Gestionar</a-button>
            <a-button type="link" size="small">Ver documentacion</a-button>
            <a-button type="link" size="small">Solicitar soporte</a-button>
          </a-space>
        </template>
      </template>
    </a-table>
  </Layout>
</template>

<script setup>
import { computed, ref } from 'vue'
import Layout from '../../Layouts/LayoutConsumidor.vue'

const services = ref([
  {
    id: 1,
    name: 'Mesa de Partes Virtual',
    organizer: 'Secretaria General',
    level: 'Institucional',
    status: 'active',
    updatedAt: '2025-10-10T10:15:00',
    validUntil: '2025-12-31T23:59:00',
  },
  {
    id: 2,
    name: 'Constancias Academicas',
    organizer: 'Direccion Academica',
    level: 'Facultad',
    status: 'pending',
    updatedAt: '2025-10-08T09:20:00',
    validUntil: '2025-11-20T23:59:00',
  },
  {
    id: 3,
    name: 'Reservas de Laboratorios',
    organizer: 'Facultad de Ingenieria',
    level: 'Programa',
    status: 'maintenance',
    updatedAt: '2025-10-05T11:05:00',
    validUntil: '2025-12-10T23:59:00',
  },
  {
    id: 4,
    name: 'Solicitudes de Practicas',
    organizer: 'Oficina de Bienestar',
    level: 'Institucional',
    status: 'active',
    updatedAt: '2025-09-30T15:45:00',
    validUntil: '2026-03-31T23:59:00',
  },
  {
    id: 5,
    name: 'Mesa de Ayuda TIC',
    organizer: 'Oficina de Tecnologias',
    level: 'Institucional',
    status: 'pending',
    updatedAt: '2025-10-12T08:40:00',
    validUntil: '2026-01-15T23:59:00',
  },
  {
    id: 6,
    name: 'Seguimiento de Solicitudes',
    organizer: 'Secretaria General',
    level: 'Institucional',
    status: 'active',
    updatedAt: '2025-10-14T12:25:00',
    validUntil: '2026-02-28T23:59:00',
  },
])

const columns = [
  { title: 'Servicio', dataIndex: 'name', key: 'service', width: 220 },
  { title: 'Nivel', dataIndex: 'level', key: 'level', width: 140 },
  { title: 'Estado', dataIndex: 'status', key: 'status', width: 160 },
  { title: 'Ultima actualizacion', dataIndex: 'updatedAt', key: 'updatedAt', width: 180 },
  { title: 'Acciones', key: 'actions', width: 200, align: 'right' },
]

const statusLabels = {
  active: 'Activo',
  pending: 'Pendiente',
  maintenance: 'En mantenimiento',
}

const statusColors = {
  active: 'green',
  pending: 'orange',
  maintenance: 'volcano',
}

const summary = computed(() => {
  const total = services.value.length
  const activos = services.value.filter((service) => service.status === 'active').length
  const pendientes = services.value.filter((service) => service.status !== 'active').length

  return {
    total,
    activos,
    pendientes,
  }
})

const formatDate = (isoDate) => {
  const date = new Date(isoDate)
  return new Intl.DateTimeFormat('es-PE', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(date)
}
</script>

<style scoped>
.page-header {
  margin-bottom: 18px;
}

.page-header h2 {
  margin: 0;
  font-size: 22px;
  font-weight: 600;
  color: #111827;
}

.page-header p {
  margin: 6px 0 0;
  color: #6b7280;
  font-size: 14px;
}

.overview {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
  margin-bottom: 20px;
}

.overview-card {
  border-radius: 12px;
  background: linear-gradient(135deg, #eef2ff 0%, #f8fafc 100%);
  border-color: #dbeafe;
}

.overview-value {
  font-size: 26px;
  font-weight: 600;
  color: #1e3a8a;
}

.overview-label {
  margin-top: 4px;
  color: #4b5563;
  font-size: 13px;
}

.services-table :deep(.ant-table-tbody > tr > td) {
  vertical-align: top;
}

.service-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.service-name {
  font-weight: 600;
  color: #111827;
}

.service-meta {
  font-size: 12px;
  color: #6b7280;
}
</style>
