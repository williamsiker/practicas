<template>
  <Layout :pagina="'Aprobación de servicios'">
    <div class="page-header">
      <div>
        <h2>Servicios en revisión</h2>
        <p>Revisa toda la información y define si se aprueba o rechaza la publicación.</p>
      </div>
      <a-button type="default" :loading="loading" @click="fetchPending">
        Recargar
      </a-button>
    </div>

    <a-table
      :data-source="pendingServices"
      :columns="columns"
      :loading="loading"
      row-key="id"
      :pagination="false"
      class="services-table"
    >
      <template #bodyCell="{ column, record }">
        <template v-if="column.key === 'service'">
          <div class="service-cell">
            <div class="service-name">{{ record.name }}</div>
            <div class="service-meta">
              {{ record.description || 'Sin descripción' }}
            </div>
            <div class="service-tags">
              <a-tag v-for="tag in record.tags" :key="`${record.id}-${tag}`" color="blue">{{ tag }}</a-tag>
            </div>
          </div>
        </template>

        <template v-else-if="column.key === 'status'">
          <a-tag color="blue">En revisión</a-tag>
        </template>

        <template v-else-if="column.key === 'schedule'">
          {{ scheduleLabels[record.schedule] || record.schedule }}
        </template>

        <template v-else-if="column.key === 'monthlyLimit'">
          <span v-if="record.monthlyLimit">
            {{ formatNumber(record.monthlyLimit) }} consultas/mes
          </span>
          <span v-else class="muted">Sin límite</span>
        </template>

        <template v-else-if="column.key === 'updatedAt'">
          {{ formatDate(record.updatedAt) }}
        </template>

        <template v-else-if="column.key === 'actions'">
          <a-space>
            <a-button type="link" size="small" @click="openDetail(record)">Ver ficha</a-button>
            <a-button type="primary" size="small" :loading="isApproving(record.id)" @click="approve(record)">
              Aprobar
            </a-button>
            <a-popconfirm
              title="¿Rechazar este servicio?"
              ok-text="Sí"
              cancel-text="No"
              :ok-button-props="{ danger: true, loading: isRejecting(record.id) }"
              @confirm="reject(record)"
            >
              <a-button danger size="small">
                Rechazar
              </a-button>
            </a-popconfirm>
          </a-space>
        </template>
      </template>

      <template #empty>
        <a-empty description="No hay servicios pendientes de aprobación." />
      </template>
    </a-table>

    <a-drawer
      v-model:open="detailVisible"
      :title="selectedService?.name || 'Ficha del servicio'"
      width="720"
      :destroy-on-close="true"
      @close="closeDetail"
    >
      <div v-if="selectedService">
        <div class="detail-section">
          <h3>Información general</h3>
          <a-descriptions bordered :column="1" size="small">
            <a-descriptions-item label="Descripción">
              {{ selectedService.description || 'Sin descripción' }}
            </a-descriptions-item>
            <a-descriptions-item label="Departamento">
              {{ selectedService.department || 'No asignado' }}
            </a-descriptions-item>
            <a-descriptions-item label="Categoría">
              {{ selectedService.category || 'No especificada' }}
            </a-descriptions-item>
            <a-descriptions-item label="Unidad responsable">
              {{ selectedService.owner || 'Sin responsable' }}
            </a-descriptions-item>
            <a-descriptions-item label="Cobertura">
              {{ selectedService.coverage || 'No especificada' }}
            </a-descriptions-item>
            <a-descriptions-item label="URL del servicio">
              <a :href="selectedService.url" target="_blank" rel="noopener">
                {{ selectedService.url }}
              </a>
            </a-descriptions-item>
            <a-descriptions-item label="Documentación">
              <span v-if="selectedService.documentationUrl">
                <a :href="selectedService.documentationUrl" target="_blank" rel="noopener">
                  Ver documentación
                </a>
              </span>
              <span v-else>Sin enlace</span>
            </a-descriptions-item>
            <a-descriptions-item label="Horario">
              {{ scheduleLabels[selectedService.schedule] || selectedService.schedule }}
            </a-descriptions-item>
            <a-descriptions-item label="Límite mensual">
              <span v-if="selectedService.monthlyLimit">
                {{ formatNumber(selectedService.monthlyLimit) }} consultas
              </span>
              <span v-else>Sin límite definido</span>
            </a-descriptions-item>
            <a-descriptions-item label="Uso promedio">
              {{ formatNumber(selectedService.usage || 0) }} solicitudes/mes
            </a-descriptions-item>
            <a-descriptions-item label="Etiquetas">
              <a-space size="small" wrap>
                <a-tag v-for="tag in selectedService.tags" :key="`${selectedService.id}-tag-${tag}`" color="blue">
                  {{ tag }}
                </a-tag>
              </a-space>
            </a-descriptions-item>
            <a-descriptions-item label="Sellos">
              <a-space size="small" wrap>
                <a-tag v-for="label in selectedService.labels" :key="`${selectedService.id}-label-${label}`" color="geekblue">
                  {{ label }}
                </a-tag>
              </a-space>
            </a-descriptions-item>
            <a-descriptions-item label="Actualizado">
              {{ formatDate(selectedService.updatedAt) }}
            </a-descriptions-item>
          </a-descriptions>
        </div>

        <div class="detail-section">
          <h3>Versiones registradas</h3>
          <a-collapse bordered>
            <a-collapse-panel
              v-for="version in selectedService.versions"
              :key="version.id"
              :header="`Versión ${version.version}`"
            >
              <a-descriptions bordered :column="1" size="small">
                <a-descriptions-item label="Estado">
                  <a-tag :color="versionStatusColors[version.status] || 'default'">
                    {{ versionStatusLabels[version.status] || version.status }}
                  </a-tag>
                </a-descriptions-item>
                <a-descriptions-item label="Fecha de liberación">
                  {{ formatDate(version.releaseDate) }}
                </a-descriptions-item>
                <a-descriptions-item label="Compatibilidad">
                  {{ version.compatibility || 'No especificada' }}
                </a-descriptions-item>
                <a-descriptions-item label="Solicitable">
                  {{ version.requestable ? 'Sí' : 'No' }}
                </a-descriptions-item>
                <a-descriptions-item label="Límite sugerido">
                  <span v-if="version.limitSuggestion">
                    {{ formatNumber(version.limitSuggestion) }} consultas/mes
                  </span>
                  <span v-else>Sin sugerencia</span>
                </a-descriptions-item>
                <a-descriptions-item label="Documentación">
                  <span v-if="version.documentation">
                    <a :href="version.documentation" target="_blank" rel="noopener">
                      Abrir documentación
                    </a>
                  </span>
                  <span v-else>Sin enlace</span>
                </a-descriptions-item>
                <a-descriptions-item label="Notas">
                  {{ version.notes || 'Sin notas adicionales' }}
                </a-descriptions-item>
              </a-descriptions>
            </a-collapse-panel>
          </a-collapse>
        </div>
      </div>
      <div v-else>
        <a-empty description="Selecciona un servicio para ver sus detalles." />
      </div>
    </a-drawer>
  </Layout>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { message } from 'ant-design-vue'
import Layout from '../../Layouts/LayoutAdmin.vue'
import { apiFetch } from '../../services/apiClient'

const pendingServices = ref([])
const loading = ref(false)
const approving = ref(new Set())
const rejecting = ref(new Set())
const detailVisible = ref(false)
const selectedService = ref(null)

const columns = [
  { title: 'Servicio', key: 'service' },
  { title: 'Tipo', dataIndex: 'type', key: 'type' },
  { title: 'Autenticación', dataIndex: 'authType', key: 'authType' },
  { title: 'Horario', key: 'schedule' },
  { title: 'Límite mensual', key: 'monthlyLimit' },
  { title: 'Actualizado', key: 'updatedAt' },
  { title: 'Estado', key: 'status' },
  { title: 'Acciones', key: 'actions', align: 'right', width: 220 },
]

const scheduleLabels = {
  office: 'Horario de oficina (08:00 - 16:00)',
  full: 'Todo el día (24/7)',
}

const versionStatusLabels = {
  available: 'Disponible',
  maintenance: 'En mantenimiento',
  deprecated: 'Fuera de servicio',
  draft: 'Borrador',
}

const versionStatusColors = {
  available: 'green',
  maintenance: 'orange',
  deprecated: 'red',
  draft: 'blue',
}

const formatDate = (isoDate) => {
  if (!isoDate) return '-'
  return new Intl.DateTimeFormat('es-PE', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(new Date(isoDate))
}

const formatNumber = (value) =>
  new Intl.NumberFormat('es-PE', { maximumFractionDigits: 0 }).format(value ?? 0)

const isApproving = (id) => approving.value.has(id)
const isRejecting = (id) => rejecting.value.has(id)

const openDetail = (service) => {
  selectedService.value = {
    ...service,
    tags: service.tags ?? [],
    labels: service.labels ?? [],
    versions: (service.versions ?? []).map((version) => ({
      ...version,
      documentation: version.documentation ?? version.documentationUrl ?? null,
      requestable: version.requestable ?? version.is_requestable ?? false,
    })),
  }
  detailVisible.value = true
}

const closeDetail = () => {
  detailVisible.value = false
  selectedService.value = null
}

const fetchPending = async () => {
  try {
    loading.value = true
    const response = await apiFetch('/api/admin/services/pending')
    if (!response.ok) throw new Error('No se pudo obtener la lista de servicios.')
    const data = await response.json()
    pendingServices.value = Array.isArray(data) ? data : data.data ?? []
  } catch (error) {
    console.error(error)
    message.error('Error al cargar los servicios pendientes.')
  } finally {
    loading.value = false
  }
}

const removeServiceAndCloseIfNeeded = (serviceId) => {
  pendingServices.value = pendingServices.value.filter((svc) => svc.id !== serviceId)
  if (selectedService.value?.id === serviceId) {
    closeDetail()
  }
}

const approve = async (service) => {
  if (isApproving(service.id)) return
  approving.value.add(service.id)

  try {
    const response = await apiFetch(`/api/admin/services/${service.slug}/approve`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
    })

    if (!response.ok) {
      const error = await response.json().catch(() => ({}))
      throw new Error(error.message || 'No se pudo aprobar el servicio.')
    }

    message.success(`Servicio "${service.name}" aprobado.`)
    removeServiceAndCloseIfNeeded(service.id)
  } catch (error) {
    console.error(error)
    message.error(error.message)
  } finally {
    approving.value.delete(service.id)
  }
}

const reject = async (service) => {
  if (isRejecting(service.id)) return
  rejecting.value.add(service.id)

  try {
    const response = await apiFetch(`/api/admin/services/${service.slug}/reject`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
    })

    if (!response.ok) {
      const error = await response.json().catch(() => ({}))
      throw new Error(error.message || 'No se pudo rechazar el servicio.')
    }

    message.info(`Servicio "${service.name}" rechazado.`)
    removeServiceAndCloseIfNeeded(service.id)
  } catch (error) {
    console.error(error)
    message.error(error.message)
  } finally {
    rejecting.value.delete(service.id)
  }
}

onMounted(fetchPending)
</script>

<style scoped>
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 20px;
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

.services-table :deep(.ant-table-tbody > tr > td) {
  vertical-align: top;
}

.service-cell {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.service-name {
  font-weight: 600;
  color: #111827;
}

.service-meta {
  font-size: 13px;
  color: #6b7280;
}

.service-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.muted {
  color: #9ca3af;
}

.detail-section {
  margin-bottom: 24px;
}

.detail-section h3 {
  margin-bottom: 12px;
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
}

.version-notes {
  margin-top: 8px;
  font-size: 13px;
  color: #4b5563;
}

@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
