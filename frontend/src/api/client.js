import axios from 'axios'
import { supported, cachedRead, cacheResponse, enqueue, keyFor } from '../sync/engine'
import { readAccount } from '../sync/storage'
import { useAuthStore } from '../store/authStore'

const networkAdapter = axios.getAdapter(axios.defaults.adapter)
const client = axios.create({ baseURL: import.meta.env.VITE_API_BASE_URL, adapter: async (config) => {
 const path=config.url.split('?')[0], method=(config.method??'GET').toUpperCase(), id=useAuthStore.getState().user?.id
 const privateRemote = path.startsWith('/chat/') || path.startsWith('/ai/')
 const account=id?await readAccount(id).catch(()=>null):null
 if(account?.enabled && supported(method,path)) {
  const data=await enqueue(method,path,config.data,config.retainedStatementFile)
  return {data,status:202,statusText:'Queued',headers:{},config,request:null}
 }
 if(method==='GET' && !privateRemote && !path.startsWith('/statement-previews') && account?.enabled && (!navigator.onLine || account.queue.length)) {
  const cached=await cachedRead(path,config.params)
  if(cached!==undefined)return {data:cached,status:200,statusText:'Offline cache',headers:{},config,request:null}
 }
 if(!navigator.onLine)throw new Error(`This view is not cached, or this action requires an online connection (${keyFor(path,config.params)}).`)
 try {
  const response=await networkAdapter(config)
  // Axios transforms JSON after the adapter returns; parse it before caching.
  if(method==='GET' && !privateRemote && !path.startsWith('/statement-previews')) { let data=response.data; if(typeof data==='string'){try{data=JSON.parse(data)}catch{/* Non-JSON response such as a downloaded PDF. */}} await cacheResponse(path,config.params,data) }
  return response
 } catch(error) {
  if(!privateRemote && !error.response && method==='GET'){const cached=await cachedRead(path,config.params);if(cached!==undefined)return {data:cached,status:200,statusText:'Cached',headers:{},config,request:null}}
  throw error
 }
} })

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
        // Retain offline queue on expiry; explicit logout clears it after review.
        useAuthStore.getState().logout()
        window.location.href = '/login'
      }
    }
    return Promise.reject(error)
  },
)

export default client
