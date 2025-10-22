const inferDevBaseUrl = () => {
  if (!import.meta.env.DEV) {
    return ''
  }

  const explicit = import.meta.env.VITE_API_DEV_PORT
  if (explicit) {
    return `http://localhost:${explicit}`
  }

  return 'http://localhost:8080'
}

const providedBaseUrl = import.meta.env.VITE_API_BASE_URL || inferDevBaseUrl()
const normalizedBaseUrl = providedBaseUrl.endsWith('/')
  ? providedBaseUrl.slice(0, -1)
  : providedBaseUrl

const buildUrl = (path = '') => {
  const normalizedPath = path.startsWith('/') ? path : `/${path}`
  if (!normalizedBaseUrl) {
    return normalizedPath
  }
  return `${normalizedBaseUrl}${normalizedPath}`
}

const shouldSendCredentials = () => {
  if (!normalizedBaseUrl) {
    return true
  }

  if (typeof window === 'undefined' || !window.location) {
    return false
  }

  try {
    const target = new URL(normalizedBaseUrl)
    return target.origin === window.location.origin
  } catch (error) {
    console.warn('No se pudo analizar la URL base de la API:', error)
    return false
  }
}

const defaultCredentials = shouldSendCredentials() ? 'same-origin' : 'omit'

export const apiFetch = (path, options = {}) => {
  const url = buildUrl(path)
  const headers = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...(options.headers || {}),
  }

  const finalOptions = {
    credentials: options.credentials ?? defaultCredentials,
    ...options,
    headers,
  }

  return fetch(url, finalOptions)
}

export const getApiBaseUrl = () => normalizedBaseUrl
