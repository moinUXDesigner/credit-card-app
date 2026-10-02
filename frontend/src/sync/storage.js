const empty = () => ({
  enabled: false,
  caches: {},
  queue: [],
  mappings: {},
  cursor: 0,
  paused: '',
  lastSync: null,
})
let database
function open() {
  if (!database)
    database = new Promise((resolve, reject) => {
      const request = indexedDB.open('ccapp-offline', 1)
      request.onupgradeneeded = () => request.result.createObjectStore('accounts')
      request.onsuccess = () => resolve(request.result)
      request.onerror = () => reject(request.error)
    })
  return database
}
export async function readAccount(id) {
  const db = await open()
  return new Promise((resolve, reject) => {
    const request = db.transaction('accounts').objectStore('accounts').get(String(id))
    request.onsuccess = () => resolve(request.result ?? empty())
    request.onerror = () => reject(request.error)
  })
}
export async function changeAccount(id, change) {
  const db = await open()
  return new Promise((resolve, reject) => {
    const tx = db.transaction('accounts', 'readwrite'),
      store = tx.objectStore('accounts'),
      request = store.get(String(id))
    let result
    request.onsuccess = () => {
      try {
        const account = request.result ?? empty()
        result = change(account)
        store.put(account, String(id))
      } catch (e) {
        reject(e)
        tx.abort()
      }
    }
    tx.oncomplete = () => {
      window.dispatchEvent(new Event('sync-change'))
      resolve(result)
    }
    tx.onerror = () => reject(tx.error)
  })
}
export async function clearAccount(id) {
  const db = await open()
  return new Promise((resolve, reject) => {
    const tx = db.transaction('accounts', 'readwrite')
    tx.objectStore('accounts').delete(String(id))
    tx.oncomplete = () => {
      window.dispatchEvent(new Event('sync-change'))
      resolve()
    }
    tx.onerror = () => reject(tx.error)
  })
}
