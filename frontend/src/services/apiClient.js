const rawBaseUrl = import.meta.env.VITE_API_BASE_URL || ''
const normalizedBaseUrl = rawBaseUrl.endsWith('/') ? rawBaseUrl.slice(0, -1) : rawBaseUrl

const buildUrl = (path = '') => {
  const normalizedPath = path.startsWith('/') ? path : `/${path}`
  if (!normalizedBaseUrl) {
    return normalizedPath
  }
  return `${normalizedBaseUrl}${normalizedPath}`
}

export const apiFetch = (path, options) => {
  const url = buildUrl(path)
  return fetch(url, options)
}

export const getApiBaseUrl = () => normalizedBaseUrl
