const inferDevBaseUrl = () => {
  if (import.meta.env.DEV) {
    return 'http://localhost:8000'
  }
  return ''
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

export const apiFetch = (path, options = {}) => {
  const url = buildUrl(path)
  const headers = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...(options.headers || {}),
  }

  const finalOptions = {
    credentials: options.credentials ?? 'include',
    ...options,
    headers,
  }

  return fetch(url, finalOptions)
}

export const getApiBaseUrl = () => normalizedBaseUrl
