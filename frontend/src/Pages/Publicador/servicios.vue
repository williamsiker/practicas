<template>
  <Layout :pagina="'Gestión de servicios'">
    <div class="page-header">
      <div>
        <h2>Gestiona tus servicios publicados</h2>
        <p>Revisa el estado de tus publicaciones y registra nuevos servicios.</p>
      </div>
      <a-button type="primary" @click="openPublish">
        Publicar nuevo servicio
      </a-button>
    </div>

    <div class="overview">
      <a-card class="overview-card" bordered>
        <div class="overview-value">{{ summary.total }}</div>
        <div class="overview-label">Servicios totales</div>
      </a-card>
      <a-card class="overview-card" bordered>
        <div class="overview-value">{{ summary.aprobados }}</div>
        <div class="overview-label">Aprobados</div>
      </a-card>
      <a-card class="overview-card" bordered>
        <div class="overview-value">{{ summary.pendientes }}</div>
        <div class="overview-label">En revisión</div>
      </a-card>
    </div>

    <a-table
      :columns="columns"
      :data-source="services"
      :loading="loading"
      :pagination="{ pageSize: 6, showSizeChanger: false }"
      row-key="id"
      :scroll="{ x: 900 }"
      class="services-table"
    >
      <template #bodyCell="{ column, record }">
        <template v-if="column.key === 'name'">
          <div class="service-cell">
            <div class="service-name">{{ record.name }}</div>
            <div class="service-meta">
              {{ record.description }}
            </div>
            <div class="service-meta">
              Publicado: {{ formatDate(record.updatedAt) }}
            </div>
          </div>
        </template>

        <template v-else-if="column.key === 'status'">
          <a-tag :color="statusColors[record.status] || 'default'">
            {{ statusLabels[record.status] || record.status }}
          </a-tag>
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

        <template v-else-if="column.key === 'actions'">
          <a-space size="small">
            <a-button type="link" size="small" @click="openDetail(record)">Ver ficha</a-button>
            <a-button type="link" size="small" @click="duplicateService(record)">Duplicar</a-button>
          </a-space>
        </template>
      </template>

      <template #empty>
        <a-empty description="No hay servicios registrados." />
      </template>
    </a-table>

    <a-modal
      v-model:open="detailVisible"
      :title="selectedService?.name || 'Ficha del servicio'"
      width="560"
      :footer="null"
      @cancel="closeDetail"
    >
      <template v-if="selectedService">
        <a-descriptions bordered :column="1" size="small">
          <a-descriptions-item label="Descripción corta">
            {{ selectedService.description }}
          </a-descriptions-item>
          <a-descriptions-item label="URL de acceso">
            <a :href="selectedService.url" target="_blank" rel="noopener">
              {{ selectedService.url }}
            </a>
          </a-descriptions-item>
          <a-descriptions-item label="Tipo de consulta">
            {{ typeLabels[selectedService.type] || selectedService.type }}
          </a-descriptions-item>
          <a-descriptions-item label="Estado">
            <a-tag :color="statusColors[selectedService.status] || 'default'">
              {{ statusLabels[selectedService.status] || selectedService.status }}
            </a-tag>
          </a-descriptions-item>
          <a-descriptions-item label="Autenticación">
            {{ authLabels[selectedService.authType] || selectedService.authType }}
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
          <a-descriptions-item label="Versión actual">
            {{ selectedService.currentVersion || 'Sin versión' }}
          </a-descriptions-item>
          <a-descriptions-item label="Última actualización">
            {{ formatDate(selectedService.updatedAt) }}
          </a-descriptions-item>
        </a-descriptions>

        <h3 class="versions-title">Versiones registradas</h3>
        <a-list
          :data-source="selectedService.versions"
          :split="true"
          item-layout="vertical"
          bordered
        >
          <template #renderItem="{ item }">
            <a-list-item>
              <a-list-item-meta
                :title="`Versión ${item.version}`"
                :description="`Liberada ${formatDate(item.releaseDate)} • Estado ${versionStatusLabels[item.status] || item.status}`"
              />
              <div class="version-extra">
                <strong>Compatibilidad:</strong> {{ item.compatibility || 'N/A' }}<br />
                <strong>Solicitable:</strong> {{ item.requestable ? 'Sí' : 'No' }}<br />
                <strong>Límite sugerido:</strong>
                <span v-if="item.limitSuggestion">
                  {{ formatNumber(item.limitSuggestion) }} consultas/mes
                </span>
                <span v-else>Sin sugerencia</span>
                <div v-if="item.notes" class="version-notes">Notas: {{ item.notes }}</div>
              </div>
            </a-list-item>
          </template>
        </a-list>
      </template>
      <template v-else>
        <p>No se encontró información del servicio.</p>
      </template>
    </a-modal>

    <a-drawer
      v-model:open="publishVisible"
      title="Publicar nuevo servicio"
      width="720"
      :destroy-on-close="true"
      @close="closePublish"
    >
      <a-form layout="vertical" :model="formState" @finish="submitPublish">
        <a-row :gutter="16">
          <a-col :span="12">
            <a-form-item
              label="Nombre"
              name="name"
              :rules="[{ required: true, message: 'Ingresa el nombre del servicio' }]"
            >
              <a-input v-model:value="formState.name" placeholder="Ej. Mesa de partes digital" />
            </a-form-item>
          </a-col>
          <a-col :span="12">
            <a-form-item
              label="Versión inicial"
              name="version"
              :rules="[{ required: true, message: 'Indica la versión inicial' }]"
            >
              <a-input v-model:value="formState.version" placeholder="Ej. 1.0.0" />
            </a-form-item>
          </a-col>
        </a-row>

        <a-form-item
          label="Descripción corta"
          name="description"
          :rules="[
            { required: true, message: 'Incluye una descripción breve' },
            { min: 20, message: 'La descripción debe tener al menos 20 caracteres' }
          ]"
        >
          <a-textarea
            v-model:value="formState.description"
            :rows="3"
            placeholder="Describe brevemente el objetivo del servicio y su público objetivo."
          />
        </a-form-item>

        <a-form-item
          label="URL de consumo o documentación"
          name="url"
          :rules="[
            { required: true, message: 'Ingresa la URL de acceso' },
            { type: 'url', message: 'Ingresa una URL válida' }
          ]"
        >
          <a-input v-model:value="formState.url" placeholder="https://mi-institucion.edu.pe/servicio" />
        </a-form-item>

        <a-row :gutter="16">
          <a-col :span="12">
            <a-form-item
              label="Tipo de consulta"
              name="type"
              :rules="[{ required: true, message: 'Selecciona el tipo de consulta' }]"
            >
              <a-select
                v-model:value="formState.type"
                :options="typeOptions"
                placeholder="Selecciona una opción"
              />
            </a-form-item>
          </a-col>
          <a-col :span="12">
            <a-form-item
              label="Estado inicial"
              name="status"
              :rules="[{ required: true, message: 'Selecciona el estado inicial' }]"
            >
              <a-select
                v-model:value="formState.status"
                :options="statusOptions"
                placeholder="Selecciona una opción"
              />
            </a-form-item>
          </a-col>
        </a-row>

        <a-form-item
          label="Tipo de autenticación"
          name="auth"
          :rules="[{ required: true, message: 'Selecciona el tipo de autenticación' }]"
        >
          <a-select
            v-model:value="formState.auth"
            :options="authOptions"
            placeholder="Selecciona una opción"
          />
        </a-form-item>

        <a-form-item
          label="Horario de atención"
          name="schedule"
          :rules="[{ required: true, message: 'Selecciona el horario de atención' }]"
        >
          <a-radio-group v-model:value="formState.schedule">
            <a-radio value="office">Horario de oficina (08:00 - 16:00)</a-radio>
            <a-radio value="full">Todo el día (24/7)</a-radio>
          </a-radio-group>
        </a-form-item>

        <a-card class="metrics-card" bordered>
          <div class="metrics-header">
            <div>
              <div class="metrics-title">Configurar límites de consumo (opcional)</div>
              <div class="metrics-subtitle">Establece un tope mensual de llamadas para seguimiento.</div>
            </div>
            <a-switch v-model:checked="formState.metricsEnabled" />
          </div>

          <transition name="metrics">
            <div v-if="formState.metricsEnabled" class="metrics-body">
              <a-form-item label="Límite de llamadas mensuales" name="metrics.monthlyCalls">
                <a-input-number
                  v-model:value="formState.metrics.monthlyCalls"
                  :min="0"
                  :step="100"
                  style="width: 100%"
                  placeholder="Ej. 5000"
                />
              </a-form-item>
            </div>
          </transition>
        </a-card>

        <a-form-item
          name="termsAccepted"
          :rules="[{ validator: validateTerms }]"
        >
          <a-checkbox v-model:checked="formState.termsAccepted">
            Acepto las condiciones de publicación y autorizo la revisión del administrador.
          </a-checkbox>
        </a-form-item>

        <div class="form-actions">
          <a-space>
            <a-button @click="closePublish">Cancelar</a-button>
            <a-button type="primary" html-type="submit" :loading="submitting">
              Enviar para revisión
            </a-button>
          </a-space>
        </div>
      </a-form>
    </a-drawer>
  </Layout>
</template>

<script setup>
import { computed, reactive, ref, onMounted } from 'vue'
import { message } from 'ant-design-vue'
import Layout from '../../Layouts/LayoutPublicador.vue'
import { apiFetch } from '../../services/apiClient'

const services = ref([])
const loading = ref(false)
const detailVisible = ref(false)
const publishVisible = ref(false)
const submitting = ref(false)
const selectedService = ref(null)

const typeOptions = [
  { label: 'API REST', value: 'api-rest' },
  { label: 'Formulario web', value: 'form-web' },
  { label: 'Archivo batch', value: 'archivo-batch' },
  { label: 'Proceso manual', value: 'proceso-manual' },
]

const statusOptions = [
  { label: 'Borrador', value: 'borrador' },
  { label: 'En revisión', value: 'revision' },
]

const authOptions = [
  { label: 'OAuth 2.0', value: 'oauth2' },
  { label: 'SSO institucional', value: 'sso' },
  { label: 'API Key', value: 'api_key' },
  { label: 'Sin autenticación', value: 'ninguna' },
]

const columns = [
  { title: 'Nombre', key: 'name', width: 260 },
  { title: 'Tipo', dataIndex: 'type', key: 'type', width: 150 },
  { title: 'Autenticación', dataIndex: 'authType', key: 'authType', width: 160 },
  { title: 'Horario', key: 'schedule', width: 200 },
  { title: 'Límite mensual', key: 'monthlyLimit', width: 160 },
  { title: 'Estado', key: 'status', width: 140 },
  { title: 'Acciones', key: 'actions', width: 160, align: 'right' },
]

const statusLabels = {
  borrador: 'Borrador',
  revision: 'En revisión',
  aprobado: 'Aprobado',
  rechazado: 'Rechazado',
}

const statusColors = {
  borrador: 'orange',
  revision: 'blue',
  aprobado: 'green',
  rechazado: 'red',
}

const scheduleLabels = {
  office: 'Horario de oficina (08:00 - 16:00)',
  full: 'Todo el día (24/7)',
}

const typeLabels = {
  'api-rest': 'API REST',
  'form-web': 'Formulario web',
  'archivo-batch': 'Archivo batch',
  'proceso-manual': 'Proceso manual',
}

const authLabels = {
  oauth2: 'OAuth 2.0',
  sso: 'SSO institucional',
  api_key: 'API Key',
  ninguna: 'Sin autenticación',
}

const versionStatusLabels = {
  available: 'Disponible',
  maintenance: 'En mantenimiento',
  deprecated: 'No disponible',
  draft: 'Borrador',
}

const formState = reactive({
  name: '',
  version: '',
  description: '',
  url: '',
  type: null,
  status: 'revision',
  auth: null,
  schedule: 'office',
  metricsEnabled: false,
  metrics: {
    monthlyCalls: null,
  },
  termsAccepted: false,
})

const validateTerms = async (_rule, value) => {
  if (value) return Promise.resolve()
  return Promise.reject(new Error('Debes aceptar las condiciones de publicación'))
}

const resetForm = () => {
  formState.name = ''
  formState.version = ''
  formState.description = ''
  formState.url = ''
  formState.type = null
  formState.status = 'revision'
  formState.auth = null
  formState.schedule = 'office'
  formState.metricsEnabled = false
  formState.metrics.monthlyCalls = null
  formState.termsAccepted = false
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

const summary = computed(() => {
  const total = services.value.length
  const aprobados = services.value.filter((service) => service.status === 'aprobado').length
  const pendientes = services.value.filter((service) => service.status === 'revision').length
  return { total, aprobados, pendientes }
})

const fetchServices = async () => {
  try {
    loading.value = true
    const response = await apiFetch('/api/publicador/services')
    if (!response.ok) throw new Error('No se pudo cargar la lista de servicios.')
    const payload = await response.json()
    const list = Array.isArray(payload) ? payload : payload.data ?? []

    services.value = list.map((service) => ({
      ...service,
      versions: service.versions ?? [],
    }))
  } catch (error) {
    console.error(error)
    message.error('Error al cargar los servicios.')
  } finally {
    loading.value = false
  }
}

const openDetail = (service) => {
  selectedService.value = service
  detailVisible.value = true
}

const closeDetail = () => {
  detailVisible.value = false
  selectedService.value = null
}

const openPublish = () => {
  resetForm()
  publishVisible.value = true
}

const closePublish = () => {
  publishVisible.value = false
  submitting.value = false
}

const submitPublish = async () => {
  if (!formState.termsAccepted) {
    message.warning('Debes aceptar las condiciones de publicación.')
    return
  }

  try {
    submitting.value = true
    const payload = {
      name: formState.name,
      short_description: formState.description,
      url: formState.url,
      type: formState.type,
      status: formState.status,
      auth_type: formState.auth,
      schedule: formState.schedule,
      monthly_limit: formState.metricsEnabled ? formState.metrics.monthlyCalls : null,
      terms_accepted: true,
      version: {
        version: formState.version,
        status: 'available',
        release_date: new Date().toISOString().slice(0, 10),
        compatibility: 'Pendiente',
        documentation_url: formState.url,
        is_requestable: true,
      },
    }

    const response = await apiFetch('/api/publicador/services', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    })

    if (!response.ok) {
      const error = await response.json().catch(() => ({}))
      const validationMessage = Array.isArray(error?.errors)
        ? error.errors.find(Boolean)
        : error?.errors
          ? Object.values(error.errors)
              .flat()
              .find(Boolean)
          : null
      const messageText = validationMessage || error.message || 'No se pudo registrar el servicio.'
      throw new Error(messageText)
    }

    const created = await response.json()
    message.success('Servicio enviado para revisión del administrador.')
    services.value = [created.data ?? created, ...services.value]
    closePublish()
  } catch (error) {
    console.error(error)
    message.error(error.message || 'Error al publicar el servicio.')
  } finally {
    submitting.value = false
  }
}

const duplicateService = async (service) => {
  try {
    const response = await apiFetch(`/api/publicador/services/${service.slug}/duplicate`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
    })

    if (!response.ok) {
      const error = await response.json().catch(() => ({}))
      throw new Error(error.message || 'No se pudo duplicar el servicio.')
    }

    const duplicated = await response.json()
    services.value = [duplicated.data ?? duplicated, ...services.value]
    message.success('Se generó una copia en borrador.')
  } catch (error) {
    console.error(error)
    message.error(error.message || 'Error al duplicar el servicio.')
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
  font-size: 13px;
  color: #6b7280;
}

.muted {
  color: #9ca3af;
}

.metrics-card {
  margin-top: 16px;
  border-radius: 12px;
  border-color: #e5e7eb;
}

.metrics-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.metrics-title {
  font-weight: 600;
  color: #1f2937;
}

.metrics-subtitle {
  color: #6b7280;
  font-size: 13px;
}

.metrics-body {
  margin-top: 16px;
}

.versions-title {
  margin-top: 20px;
  margin-bottom: 12px;
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
}

.version-extra {
  font-size: 13px;
  color: #4b5563;
  line-height: 1.4;
}

.version-notes {
  margin-top: 6px;
}

.form-actions {
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
  .page-header {
    flex-direction: column;
    align-items: stretch;
  }

  .overview {
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  }
}
</style>
