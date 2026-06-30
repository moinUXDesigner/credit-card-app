import axios from 'axios'
import { useAuthStore } from '../store/authStore'

const client = axios.create({ baseURL: import.meta.env.VITE_API_BASE_URL })

client.interceptors.request.use((config) => {
  const token = useAuthStore.getState().token
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

client.interceptors.response.use(
  (res) => res,
  async (error) => {
    const original = error.config
    if (error.response?.status === 401 && original && !original._retry) {
      original._retry = true
      try {
        const { data } = await axios.post(
          `${import.meta.env.VITE_API_BASE_URL}/auth/refresh`,
          {},
          { headers: { Authorization: `Bearer ${useAuthStore.getState().token}` } },
        )
        useAuthStore.getState().setAuth(data.access_token, useAuthStore.getState().user)
        original.headers.Authorization = `Bearer ${data.access_token}`
        return client(original)
      } catch {
        useAuthStore.getState().logout()
        window.location.href = '/login'
      }
    }
    return Promise.reject(error)
  },
)

export default client
