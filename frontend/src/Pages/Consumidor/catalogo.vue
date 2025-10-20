<template>
  <Layout :pagina="'Catálogo de servicios'">
    <div class="page-header">
      <div>
        <h2>Explora servicios disponibles</h2>
        <p>Filtra el catálogo rápido según lo que más te interesa.</p>
      </div>
      <a-button type="default" :loading="loading" @click="fetchServices">
        Recargar
      </a-button>
    </div>

    <div class="filters">
      <a-space size="middle" :wrap="true">
        <a-badge
          v-for="filter in filters"
          :key="filter.value"
          :count="filterCounts[filter.value]"
          :overflow-count="99"
        >
          <a-button
            type="text"
            class="filter-badge"
            :class="{ 'filter-badge--active': activeFilter === filter.value }"
            @click="setFilter(filter.value)"
          >
            {{ filter.label }}
          </a-button>
        </a-badge>
      </a-space>
    </div>

    <a-table
      :columns="columns"
      :data-source="filteredServices"
      :loading="loading"
      :pagination="{ pageSize: 5, showSizeChanger: false }"
      row-key="id"
      :scroll="{ x: 768 }"
      class="services-table"
    >
      <template #bodyCell="{ column, record }">
        <template v-if="column.key === 'service'">
          <div class="service-cell">
            <div class="service-name">{{ record.name }}</div>
            <div class="service-meta">
              {{ record.department || 'Sin departamento' }} • Actualizado {{ formatDate(record.updatedAt) }}
            </div>
            <div class="service-tags">
              <a-tag v-for="label in record.labels" :key="label" color="blue">{{ label }}</a-tag>
            </div>
          </div>
        </template>

        <template v-else-if="column.key === 'category'">
          <a-tag color="geekblue">{{ record.category || 'Sin categoría' }}</a-tag>
        </template>

        <template v-else-if="column.key === 'status'">
          <a-tag :color="record.status === 'aprobado' ? 'green' : 'orange'">
            {{ record.status }}
          </a-tag>
        </template>

        <template v-else-if="column.key === 'usage'">
          {{ formatNumber(record.usage ?? 0) }} solicitudes/mes
        </template>

        <template v-else-if="column.key === 'tags'">
          <a-space size="small" wrap>
            <a-tag v-for="tag in record.tags" :key="tag" color="blue">
              {{ tag }}
            </a-tag>
          </a-space>
        </template>

        <template v-else-if="column.key === 'actions'">
          <a-space size="small">
            <a-button type="link" size="small" @click="openDetail(record)">Ver detalle</a-button>
            <a-button type="link" size="small" @click="openRequest(record)">Solicitar</a-button>
            <a-button type="link" size="small" @click="openDocumentation(record)">Ver documentación</a-button>
          </a-space>
        </template>
      </template>

      <template #empty>
        <a-empty description="No hay servicios que coincidan con el filtro." />
      </template>
    </a-table>

    <a-modal
      v-model:open="detailModalVisible"
      :title="selectedService?.name || 'Detalle del servicio'"
      width="520"
      :footer="null"
      @cancel="closeDetail"
    >
      <div v-if="selectedService">
        <p class="modal-intro">{{ selectedService.description }}</p>
        <a-descriptions size="small" column="1" bordered>
          <a-descriptions-item label="Versión actual">
            {{ selectedService.currentVersion || 'Sin definir' }}
          </a-descriptions-item>
          <a-descriptions-item label="Unidad responsable">
            {{ selectedService.owner || 'No asignado' }}
          </a-descriptions-item>
          <a-descriptions-item label="Cobertura">
            {{ selectedService.coverage || 'No especificado' }}
          </a-descriptions-item>
          <a-descriptions-item label="Horario de atención">
            {{ scheduleLabels[selectedService.schedule] || selectedService.schedule }}
          </a-descriptions-item>
          <a-descriptions-item label="Límite mensual">
            <span v-if="selectedService.monthlyLimit">
              {{ formatNumber(selectedService.monthlyLimit) }} consultas
            </span>
            <span v-else>Sin límite</span>
          </a-descriptions-item>
          <a-descriptions-item label="Última actualización">
            {{ formatDate(selectedService.updatedAt) }}
          </a-descriptions-item>
        </a-descriptions>
      </div>
      <div v-else>
        <p>No se encontró información del servicio.</p>
      </div>
    </a-modal>

    <a-modal
      v-model:open="requestModalVisible"
      :title="selectedService ? `Solicitar ${selectedService.name}` : 'Solicitar versión'"
      width="640"
      :footer="null"
      @cancel="closeRequest"
    >
      <div v-if="selectedService">
        <p class="modal-intro">
          Selecciona la versión que deseas solicitar o revisa su documentación de referencia.
        </p>
        <a-list
          :data-source="selectedService.versions"
          item-layout="vertical"
          :split="false"
        >
          <template #renderItem="{ item }">
            <a-list-item class="version-item">
              <template #actions>
                <a-tag :color="versionStatusColors[item.status] || 'default'">
                  {{ versionStatusLabels[item.status] || item.status }}
                </a-tag>
                <a-button type="link" size="small" @click="openVersionDocumentation(item)">
                  Ver documentación
                </a-button>
                <a-button
                  type="primary"
                  size="small"
                  :disabled="!item.requestable"
                  @click="prepareRequest(item)"
                >
                  Solicitar
                </a-button>
              </template>
              <a-list-item-meta
                :title="`Versión ${item.version}`"
                :description="`Liberada ${formatDate(item.releaseDate)} • Compatibilidad ${item.compatibility || 'N/A'}`"
              />
              <div class="version-notes">
                {{ item.notes || 'Sin notas adicionales.' }}
              </div>
              <div class="version-suggestion" v-if="item.limitSuggestion">
                Sugerencia de límite: {{ formatNumber(item.limitSuggestion) }} consultas/mes
              </div>
            </a-list-item>
          </template>
        </a-list>
      </div>
      <div v-else>
        <p>No hay versiones disponibles para mostrar.</p>
      </div>
    </a-modal>

    <a-modal
      v-model:open="requestFormVisible"
      :title="selectedVersion ? `Solicitud para versión ${selectedVersion.version}` : 'Solicitud personalizada'"
      width="520"
      :footer="null"
      @cancel="closeRequestForm"
    >
      <div v-if="selectedService && selectedVersion">
        <p class="modal-intro">
          Indica el horario y límite mensual que necesitas. El publicador evaluará estos requerimientos antes de aprobar.
        </p>
        <a-form layout="vertical" :model="requestForm" @finish="submitRequest">
          <a-form-item label="Horario preferido" name="schedule">
            <a-radio-group v-model:value="requestForm.schedule">
              <a-radio value="office">Horario de oficina (08:00 - 16:00)</a-radio>
              <a-radio value="full">Todo el día (24/7)</a-radio>
              <a-radio value="custom">Horario personalizado</a-radio>
            </a-radio-group>
          </a-form-item>

          <transition name="metrics">
            <div v-if="requestForm.schedule === 'custom'" class="custom-schedule">
              <a-row :gutter="12">
                <a-col :span="12">
                  <a-form-item label="Desde" name="customStart">
                    <a-input v-model:value="requestForm.customStart" placeholder="HH:MM" />
                  </a-form-item>
                </a-col>
                <a-col :span="12">
                  <a-form-item label="Hasta" name="customEnd">
                    <a-input v-model:value="requestForm.customEnd" placeholder="HH:MM" />
                  </a-form-item>
                </a-col>
              </a-row>
            </div>
          </transition>

          <a-form-item
            label="Límite mensual propuesto"
            name="monthlyLimit"
            :rules="[{ required: true, message: 'Indica el límite mensual' }]"
          >
            <a-input-number
              v-model:value="requestForm.monthlyLimit"
              :min="1"
              :step="50"
              style="width: 100%"
              placeholder="Ej. 3000"
            />
          </a-form-item>

          <a-form-item label="Notas para el publicador" name="notes">
            <a-textarea
              v-model:value="requestForm.notes"
              :rows="3"
              placeholder="Opcional: describe casos de uso o justifica el horario solicitado."
            />
          </a-form-item>

          <div class="request-form-actions">
            <a-space>
              <a-button @click="closeRequestForm">Cancelar</a-button>
              <a-button type="primary" html-type="submit" :loading="submittingRequest">
                Enviar solicitud
              </a-button>
            </a-space>
          </div>
        </a-form>
      </div>
      <div v-else>
        <p>Selecciona una versión para continuar con la solicitud.</p>
      </div>
    </a-modal>
  </Layout>
</template>

<script setup>
import { computed, reactive, ref, onMounted } from 'vue'
import { message } from 'ant-design-vue'
import Layout from '../../Layouts/LayoutConsumidor.vue'

const loading = ref(false)
const submittingRequest = ref(false)

const filters = [
  { label: 'Recientes', value: 'recientes' },
  { label: 'Más usados', value: 'masUsados' },
  { label: 'Destacados', value: 'destacados' },
  { label: 'Todo el catálogo', value: 'todos' },
]

const columns = [
  { title: 'Servicio', key: 'service' },
  { title: 'Categoría', key: 'category' },
  { title: 'Estado', key: 'status' },
  { title: 'Uso promedio', key: 'usage' },
  { title: 'Etiquetas', key: 'tags' },
  { title: 'Acciones', key: 'actions', width: 200, align: 'right' },
]

const scheduleLabels = {
  office: 'Horario de oficina (08:00 - 16:00)',
  full: 'Todo el día (24/7)',
}

const versionStatusLabels = {
  available: 'Disponible',
  maintenance: 'En mantenimiento',
  deprecated: 'Fuera de servicio',
}

const versionStatusColors = {
  available: 'green',
  maintenance: 'orange',
  deprecated: 'red',
}

const activeFilter = ref('recientes')
const services = ref([])
const detailModalVisible = ref(false)
const requestModalVisible = ref(false)
const requestFormVisible = ref(false)
const selectedService = ref(null)
const selectedVersion = ref(null)

const requestForm = reactive({
  schedule: 'office',
  customStart: '',
  customEnd: '',
  monthlyLimit: null,
  notes: '',
})

const fetchServices = async () => {
  try {
    loading.value = true
    const response = await fetch('/api/consumidor/services')
    if (!response.ok) throw new Error('No se pudo cargar el catálogo.')
    const payload = await response.json()
    const list = Array.isArray(payload) ? payload : payload.data ?? []

    services.value = list.map((service) => ({
      ...service,
      tags: service.tags ?? [],
      labels: service.labels ?? [],
      versions: (service.versions ?? []).map((version) => ({
        ...version,
        documentation: version.documentation ?? version.documentationUrl ?? null,
      })),
    }))
  } catch (error) {
    console.error(error)
    message.error('Error al cargar los servicios.')
  } finally {
    loading.value = false
  }
}

const filterCounts = computed(() => {
  const base = {
    recientes: 0,
    masUsados: 0,
    destacados: 0,
    todos: services.value.length,
  }

  services.value.forEach((service) => {
    if (service.tags.includes('recientes')) base.recientes += 1
    if (service.tags.includes('masUsados')) base.masUsados += 1
    if (service.tags.includes('destacados')) base.destacados += 1
  })

  return base
})

const filteredServices = computed(() => {
  const sorted = [...services.value].sort(
    (a, b) => new Date(b.updatedAt || 0) - new Date(a.updatedAt || 0),
  )

  if (activeFilter.value === 'todos') return sorted
  return sorted.filter((service) => service.tags.includes(activeFilter.value))
})

const setFilter = (value) => {
  activeFilter.value = value
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

const resetRequestForm = () => {
  requestForm.schedule = 'office'
  requestForm.customStart = ''
  requestForm.customEnd = ''
  requestForm.monthlyLimit = null
  requestForm.notes = ''
}

const openDetail = (service) => {
  selectedService.value = service
  detailModalVisible.value = true
}

const closeDetail = () => {
  detailModalVisible.value = false
  if (!requestModalVisible.value) {
    selectedService.value = null
  }
}

const openRequest = (service) => {
  selectedService.value = service
  selectedVersion.value = null
  resetRequestForm()
  requestModalVisible.value = true
  requestFormVisible.value = false
}

const closeRequest = () => {
  requestModalVisible.value = false
  requestFormVisible.value = false
  selectedVersion.value = null
  resetRequestForm()
  if (!detailModalVisible.value) {
    selectedService.value = null
  }
}

const openDocumentation = (service) => {
  const url = service.documentationUrl || service.url
  if (url && typeof window !== 'undefined') {
    window.open(url, '_blank', 'noopener')
  } else {
    message.info('No hay documentación disponible para este servicio.')
  }
}

const openVersionDocumentation = (version) => {
  const url = version.documentation || version.documentationUrl
  if (url && typeof window !== 'undefined') {
    window.open(url, '_blank', 'noopener')
  } else {
    message.info('No se encontró documentación para esta versión.')
  }
}

const prepareRequest = (version) => {
  if (!version.requestable) {
    message.warning('Esta versión no admite nuevas solicitudes.')
    return
  }

  selectedVersion.value = version
  resetRequestForm()

  if (version.limitSuggestion) {
    requestForm.monthlyLimit = version.limitSuggestion
  } else if (selectedService.value?.monthlyLimit) {
    requestForm.monthlyLimit = selectedService.value.monthlyLimit
  }

  requestFormVisible.value = true
}

const closeRequestForm = () => {
  requestFormVisible.value = false
  selectedVersion.value = null
  resetRequestForm()
}

const submitRequest = async () => {
  if (!selectedService.value || !selectedVersion.value) {
    message.error('Selecciona un servicio y una versión antes de enviar la solicitud.')
    return
  }

  if (!requestForm.monthlyLimit || requestForm.monthlyLimit <= 0) {
    message.warning('Indica el límite mensual de consultas que necesitas.')
    return
  }

  if (
    requestForm.schedule === 'custom' &&
    (!requestForm.customStart.trim() || !requestForm.customEnd.trim())
  ) {
    message.warning('Completa el horario personalizado con hora de inicio y fin.')
    return
  }

  try {
    submittingRequest.value = true
    const response = await fetch(
      `/api/consumidor/services/${selectedService.value.slug}/versions/${selectedVersion.value.id}/requests`,
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          schedule: requestForm.schedule,
          customStart: requestForm.customStart || null,
          customEnd: requestForm.customEnd || null,
          monthlyLimit: requestForm.monthlyLimit,
          notes: requestForm.notes || null,
        }),
      },
    )

    if (!response.ok) {
      const error = await response.json().catch(() => ({}))
      throw new Error(error.message || 'No se pudo registrar la solicitud.')
    }

    message.success(
      `Se envió la solicitud para ${selectedService.value.name} (versión ${selectedVersion.value.version}).`,
    )
    closeRequestForm()
    closeRequest()
  } catch (error) {
    console.error(error)
    message.error(error.message)
  } finally {
    submittingRequest.value = false
  }
}

onMounted(fetchServices)
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

.filters {
  margin-bottom: 20px;
}

.filter-badge {
  min-width: 160px;
  justify-content: flex-start;
  padding: 8px 16px;
  border-radius: 999px;
  background: #f4f5f7;
  color: #1f2937;
  transition: all 0.2s ease;
}

.filter-badge:hover {
  background: #e1e7ff;
  color: #1d4ed8;
}

.filter-badge--active {
  background: #1d4ed8 !important;
  color: #ffffff !important;
  box-shadow: 0 8px 16px rgba(29, 78, 216, 0.18);
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

.modal-intro {
  margin-bottom: 16px;
  color: #4b5563;
  font-size: 14px;
}

.version-item {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 12px;
  margin-bottom: 12px;
}

.version-item :deep(.ant-list-item-action) {
  margin-left: 0;
  gap: 12px;
  display: flex;
  align-items: center;
}

.version-notes {
  margin-top: 6px;
  color: #4b5563;
  font-size: 13px;
}

.version-suggestion {
  margin-top: 4px;
  font-size: 12px;
  color: #2563eb;
  font-weight: 500;
}

.custom-schedule :deep(.ant-form-item) {
  margin-bottom: 12px;
}

.request-form-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 18px;
}

.metrics-enter-active,
.metrics-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.metrics-enter-from,
.metrics-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

@media (max-width: 768px) {
  .filter-badge {
    min-width: auto;
    width: 100%;
    text-align: left;
  }
}
</style>
