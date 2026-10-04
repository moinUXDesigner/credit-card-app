import { useAuthStore } from '../store/authStore'
import { readAccount, changeAccount, clearAccount } from './storage'
const base = import.meta.env.VITE_API_BASE_URL
const currentId = () => useAuthStore.getState().user?.id
const channel = typeof BroadcastChannel !== 'undefined' ? new BroadcastChannel('ccapp-sync') : null
channel?.addEventListener('message', () => {
  window.dispatchEvent(new Event('sync-change'))
  window.dispatchEvent(new Event('sync-data'))
})
const announce = () => {
  window.dispatchEvent(new Event('sync-change'))
  window.dispatchEvent(new Event('sync-data'))
  channel?.postMessage('changed')
}
export const keyFor = (path, params) =>
  path +
  (params && Object.keys(params).length
    ? '?' + new URLSearchParams(Object.entries(params).sort()).toString()
    : '')
export function supported(method, path) {
  return (
    (method === 'POST' &&
      /^\/cards(?:\/import|\/[^/]+\/(?:benefits|spend-entries|statements))?$/.test(path)) ||
    (['PUT', 'PATCH', 'DELETE'].includes(method) && /^\/(cards|benefits)\/[^/]+$/.test(path)) ||
    (method === 'DELETE' && /^\/statements\/[^/]+$/.test(path)) ||
    (method === 'POST' && /^\/benefits\/[^/]+\/mark-used$/.test(path))
  )
}
function recordPath(path) {
  const m = path.match(/^\/(cards|benefits|statements)\/([^/]+)/)
  return m && m[2] !== 'import' ? `/${m[1]}/${m[2]}` : null
}
function model(account, path) {
  const match = path?.match(/^\/(cards|benefits|statements)\/([^/]+)/)
  if (!match) return null
  if (match[1] === 'cards') return account.caches[`/cards/${match[2]}`]
  for (const [key, value] of Object.entries(account.caches))
    if (key.endsWith('/' + match[1]) && Array.isArray(value)) {
      const found = value.find((x) => String(x.id) === match[2])
      if (found) return found
    }
  return null
}
function mappedPath(path, mapping) {
  return path
    .split('/')
    .map((p) => mapping[p] ?? p)
    .join('/')
}
function recalculate(card) {
  const pct =
    card.total_limit > 0 ? (Number(card.current_outstanding) / Number(card.total_limit)) * 100 : 0
  return {
    ...card,
    utilization_percentage: pct,
    utilization_band:
      pct < 30
        ? 'good'
        : pct < 50
          ? 'caution'
          : pct < 75
            ? 'avoid_further_use'
            : 'urgent_repayment',
    utilization_message: 'Local estimate; recalculated after sync.',
    waiver_remaining_spend: Math.max(
      0,
      Number(card.waiver_spend_required ?? 0) - Number(card.waiver_spend_completed ?? 0),
    ),
    waiver_progress_percentage:
      card.waiver_spend_required > 0
        ? Math.min(100, (card.waiver_spend_completed / card.waiver_spend_required) * 100)
        : 100,
    waiver_months_left: 12,
    waiver_suggested_monthly_spend: 0,
  }
}
function optimistic(account, op) {
  const path = mappedPath(op.path, account.mappings),
    payload = op.payload,
    id = account.mappings[op.tempId] ?? op.tempId
  const cached = model(account, path)
  if (path === '/cards' && op.method === 'POST') {
    const card = recalculate({
      current_outstanding: 0,
      annual_fee_amount: 0,
      waiver_spend_completed: 0,
      reward_point_balance: 0,
      reward_point_value_estimate: 0,
      ...payload,
      id,
      revision: 1,
      permission: 'owner',
      pending: true,
    })
    account.caches['/cards'] = [...(account.caches['/cards'] ?? []), card]
    account.caches[`/cards/${id}`] = card
    for (const child of ['benefits', 'statements', 'spend-entries'])
      account.caches[`/cards/${id}/${child}`] = []
    return card
  }
  if (/^\/cards\/[^/]+$/.test(path) && cached) {
    if (op.method === 'DELETE') {
      account.caches['/cards'] = (account.caches['/cards'] ?? []).filter(
        (c) => String(c.id) !== String(cached.id),
      )
      for (const key of Object.keys(account.caches))
        if (key === path || key.startsWith(path + '/')) delete account.caches[key]
      return null
    }
    const card = recalculate({ ...cached, ...payload, pending: true })
    account.caches[path] = card
    account.caches['/cards'] = (account.caches['/cards'] ?? []).map((c) =>
      String(c.id) === String(card.id) ? card : c,
    )
    return card
  }
  const create = path.match(/^\/cards\/([^/]+)\/(benefits|statements|spend-entries)$/)
  if (create) {
    const [, cardId, type] = create
    const list = account.caches[path] ?? []
    let item = {
      ...payload,
      id,
      card_id: account.mappings[cardId] ?? cardId,
      revision: 1,
      pending: true,
    }
    if (type === 'benefits')
      item = {
        used_count: 0,
        estimated_value: null,
        ...item,
        remaining: Number(payload.total_allowed) - Number(payload.used_count ?? 0),
      }
    if (type === 'statements' && payload.preview_id) {
      const staged = list.find((s) => s.preview_id === payload.preview_id)
      item = { ...staged, ...item, id: staged?.id ?? id, original_filename: staged?.original_filename ?? (op.filename || 'Reviewed statement import'), analysis_status: 'queued', analysis_message: 'Reviewed import queued for sync.', total_due: payload.summary?.total_due ?? null, transactions: [] }
    } else if (type === 'statements')
      item = {
        ...item,
        original_filename: op.filename,
        analysis_status: 'queued',
        analysis_message: 'Awaiting analysis/review after sync. No balances or spend have been changed.',
        total_due: null,
        minimum_due: null,
        transactions: [],
      }
    if (type === 'spend-entries') {
      const existing = list.find(
        (x) =>
          Number(x.year) === Number(payload.year) &&
          Number(x.month) === Number(payload.month) &&
          (x.category ?? null) === (payload.category ?? null),
      )
      if (existing) item = { ...existing, ...payload, pending: true }
    }
    account.caches[path] = [...list.filter((x) => String(x.id) !== String(item.id)), item]
    return item
  }
  if (cached) {
    const type = path.split('/')[1],
      key = `/cards/${cached.card_id}/${type}`
    const list = account.caches[key] ?? []
    if (op.method === 'DELETE') {
      account.caches[key] = list.filter((x) => String(x.id) !== String(cached.id))
      return null
    }
    const item = { ...cached, ...payload, pending: true }
    if (path.endsWith('/mark-used')) {
      item.used_count = Number(item.used_count) + 1
      item.remaining = Math.max(0, Number(item.total_allowed) - item.used_count)
    }
    account.caches[key] = list.map((x) => (String(x.id) === String(item.id) ? item : x))
    return item
  }
  return {
    pending: true,
    imported: 0,
    failed: 0,
    errors: [],
    message: 'Queued for server processing.',
  }
}
export async function cachedRead(path, params) {
  const id = currentId()
  if (!id) return undefined
  const account = await readAccount(id)
  return account.enabled ? account.caches[keyFor(path, params)] : undefined
}
export async function cacheResponse(path, params, data) {
  const id = currentId()
  if (!id) return
  await changeAccount(id, (a) => {
    if (a.enabled && !a.queue.length && !/^\/(admin|invitations|sync)/.test(path)) {
      a.caches[keyFor(path, params)] = data
      a.baseline = structuredClone(a.caches)
    }
  })
}
async function fetchApi(path, options = {}) {
  const auth = useAuthStore.getState()
  const response = await fetch(base + path, {
    ...options,
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${auth.token}`,
      ...options.headers,
    },
  })
  const data =
    response.status === 204
      ? null
      : await response.json().catch(() => ({ message: 'Invalid server response.' }))
  if (!response.ok) {
    const e = new Error(data?.message ?? 'Request failed.')
    e.status = response.status
    e.data = data
    throw e
  }
  return data
}
function installSnapshot(account, snapshot) {
  // Reset all cached endpoints so revocation cannot leave stale reports or files.
  account.caches = { '/cards': snapshot.cards }
  for (const card of snapshot.cards) {
    account.caches[`/cards/${card.id}`] = card
    for (const [source, name] of [
      ['benefits', 'benefits'],
      ['statements', 'statements'],
      ['spend_entries', 'spend-entries'],
    ])
      account.caches[`/cards/${card.id}/${name}`] = snapshot[source].filter(
        (x) => String(x.card_id) === String(card.id),
      )
  }
  account.baseline = structuredClone(account.caches)
  account.cursor = snapshot.cursor
  account.lastSync = new Date().toISOString()
  account.paused = ''
  for (const op of account.queue) {
    const path = mappedPath(op.path, account.mappings),
      target = model(account, path)
    if (op.state === 'blocked' || (recordPath(path) && !target && !path.includes('local-')))
      continue
    optimistic(account, op)
  }
}
async function pull(id) {
  const account = await readAccount(id)
  const snapshot = await fetchApi(`/sync/changes?cursor=${account.cursor}`)
  if (currentId() !== id) return
  await changeAccount(id, (a) => {
    // Block inaccessible items, retaining payloads for review but never rendering revoked data.
    const cards = new Set(snapshot.cards.map((c) => String(c.id)))
    for (const op of a.queue) {
      const cid = op.cardId && String(a.mappings[op.cardId] ?? op.cardId)
      if (cid && !cid.startsWith('local-') && !cards.has(cid)) {
        op.state = 'blocked'
        op.error = 'Card deleted or sharing revoked.'
      }
    }
    installSnapshot(a, snapshot)
  })
  const auth = useAuthStore.getState()
  if (currentId() === id) auth.setAuth(auth.token, snapshot.user)
}
async function cacheShell() {
  if (!('serviceWorker' in navigator))
    throw new Error('This browser does not support offline pages.')
  const registration = await navigator.serviceWorker.ready
  const urls = [
    ...new Set([
      '/',
      '/index.html',
      ...performance
        .getEntriesByType('resource')
        .map((entry) => entry.name)
        .filter((value) => {
          const url = new URL(value)
          return (
            url.origin === location.origin &&
            /^(\/src\/|\/@|\/node_modules\/|\/assets\/)/.test(url.pathname)
          )
        }),
    ]),
  ]
  await new Promise((resolve, reject) => {
    const channel = new MessageChannel()
    const timer = setTimeout(
      () => reject(new Error('Unable to cache the app shell. Retry while connected.')),
      15000,
    )
    channel.port1.onmessage = (event) => {
      clearTimeout(timer)
      if (event.data.ok) resolve()
      else reject(new Error('Unable to cache the app shell. Retry while connected.'))
    }
    registration.active.postMessage({ type: 'CACHE_SHELL', urls }, [channel.port2])
  })
}
export async function enableOffline() {
  const id = currentId()
  if (!id) throw new Error('Log in first.')
  if (!navigator.onLine) throw new Error('Connect once to enable offline storage.')
  await cacheShell()
  const snapshot = await fetchApi('/sync/bootstrap')
  await changeAccount(id, (a) => {
    a.enabled = true
    installSnapshot(a, snapshot)
  })
  await navigator.storage?.persist?.()
  announce()
}
export async function enqueue(method, path, data, retainedFile) {
  const id = currentId()
  if (!id) throw new Error('Log in first.')
  let payload = {},
    file = null,
    filename = ''
  if (data instanceof FormData) {
    for (const [key, value] of data.entries()) {
      if (value instanceof Blob) {
        file = value
        filename = value.name ?? 'upload'
      } else payload[key] = value
    }
  } else payload = typeof data === 'string' ? JSON.parse(data) : (data ?? {})
  if (retainedFile instanceof Blob) { file = retainedFile; filename = retainedFile.name ?? 'statement.pdf' }
  const op = {
    operation_id: crypto.randomUUID(),
    method,
    path,
    payload,
    file,
    filename,
    tempId: 'local-' + crypto.randomUUID(),
    state: 'pending',
    attempts: 0,
    nextRetry: 0,
    createdAt: new Date().toISOString(),
  }
  let result
  await changeAccount(id, (account) => {
    if (!account.enabled) throw new Error('Enable offline storage first.')
    if (
      account.queue.some((x) => x.file && (x.file.size ?? 0) > 0) &&
      file &&
      file.size + account.queue.reduce((s, x) => s + (x.file?.size ?? 0), 0) > 100 * 1024 * 1024
    )
      throw new Error(
        'Pending files exceed the 100 MB limit. Sync or discard existing uploads first.',
      )
    if (file && file.size > 100 * 1024 * 1024)
      throw new Error('Pending files exceed the 100 MB limit.')
    if (file && file.size > 15 * 1024 * 1024) throw new Error('Files must be no larger than 15 MB.')
    const target = model(account, path),
      parent = path.match(/^\/cards\/([^/]+)/)
    op.cardId = parent && parent[1] !== 'import' ? parent[1] : target?.card_id
    if (target) {
      if (target.permission === 'viewer') throw new Error('This shared card is read-only.')
      if (method === 'DELETE' && /^\/cards\/[^/]+$/.test(path) && target.permission !== 'owner')
        throw new Error('Only the owner can delete a card.')
    }
    op.revision = target?.revision ?? null
    const ref = recordPath(path)
    const previous = [...account.queue].reverse().find((x) => recordPath(x.path) === ref && ref)
    if (previous) op.dependsOn = previous.operation_id
    if (method === 'DELETE' && /^\/cards\/local-/.test(path)) {
      const temp = path.split('/')[2]
      account.queue = account.queue.filter((x) => x.tempId !== temp && x.cardId !== temp)
      result = optimistic(account, op)
      return
    }
    if (method === 'DELETE' && /^\/cards\/[^/]+$/.test(path))
      account.queue = account.queue.filter((x) => x.cardId !== op.cardId || x.path === path)
    if (method === 'DELETE' && /^\/(benefits|statements)\/local-/.test(path)) {
      const temp = path.split('/')[2],
        creator = account.queue.find((x) => x.tempId === temp)
      if (creator) {
        account.queue = account.queue.filter((x) => x.tempId !== temp && !x.path.includes(temp))
        result = optimistic(account, op)
        return
      }
    }
    account.queue.push(op)
    result = optimistic(account, op)
  })
  announce()
  if (navigator.onLine) void flush().catch(() => {})
  return result
}
let running
export async function flush() {
  if (running) return running
  const id = currentId()
  if (!id || !navigator.onLine) return
  const run = async () => {
    let account = await readAccount(id)
    if (!account.enabled) return
    try {
      await pull(id)
    } catch (e) {
      await changeAccount(id, (a) => {
        a.paused = e.status === 401 ? 'Session expired. Log in again to resume.' : e.message
      })
      announce()
      return
    }
    for (let i = 0; i < 100; i++) {
      if (currentId() !== id || !navigator.onLine) break
      account = await readAccount(id)
      const op = account.queue.find(
        (x) =>
          x.state === 'pending' &&
          x.nextRetry <= Date.now() &&
          !account.queue.some((y) => y.operation_id === x.dependsOn),
      )
      if (!op) break
      const path = mappedPath(op.path, account.mappings)
      if (path.includes('local-')) break
      const form = new FormData()
      form.append('operation_id', op.operation_id)
      form.append('method', op.method)
      form.append('path', path)
      // Multipart payload is JSON decoded by the backend sync endpoint.
      form.append('payload_json', JSON.stringify(op.payload))
      if (op.revision) form.append('revision', String(op.revision))
      if (op.file) form.append('file', op.file, op.filename)
      try {
        const result = await fetchApi('/sync/mutations', { method: 'POST', body: form })
        if (currentId() !== id) break
        await changeAccount(id, (a) => {
          if (result.data?.id && op.method === 'POST') a.mappings[op.tempId] = result.data.id
          a.queue = a.queue.filter((x) => x.operation_id !== op.operation_id)
          a.history = [
            {
              operation_id: op.operation_id,
              path,
              finished: new Date().toISOString(),
              summary:
                result.data?.imported !== undefined
                  ? `Imported ${result.data.imported}; failed ${result.data.failed ?? 0}.`
                  : result.data?.analysis_status
                    ? `Statement ${result.data.analysis_status}: ${result.data.analysis_message ?? ''}`
                    : 'Change synchronized.',
            },
            ...(a.history ?? []),
          ].slice(0, 20)
        })
        await pull(id)
        await changeAccount(id, (a) => {
          for (const next of a.queue)
            if (next.dependsOn === op.operation_id) {
              next.revision = model(a, mappedPath(next.path, a.mappings))?.revision ?? next.revision
              delete next.dependsOn
            }
        })
      } catch (e) {
        await changeAccount(id, (a) => {
          const item = a.queue.find((x) => x.operation_id === op.operation_id)
          if (!item) return
          item.error = e.message
          item.server = e.data?.server
          item.serverRevision = e.data?.revision
          if (e.status === 409) item.state = 'conflict'
          else if ([403, 404, 410].includes(e.status)) item.state = 'blocked'
          else if (e.status === 422) item.state = 'failed'
          else if (e.status === 401) {
            a.paused = 'Session expired. Log in again to resume.'
            item.state = 'pending'
            item.nextRetry = Date.now() + 60000
          } else {
            item.attempts++
            item.nextRetry = Date.now() + Math.min(60000, 1000 * 2 ** Math.min(item.attempts, 6))
          }
        })
        if (!e.status || e.status === 401 || e.status >= 500) break
      }
    }
    announce()
  }
  running = (navigator.locks ? navigator.locks.request(`ccapp-flush-${id}`, run) : run()).finally(
    () => {
      running = null
    },
  )
  return running
}
export async function discardOperation(operationId) {
  const id = currentId()
  await changeAccount(id, (a) => {
    const remove = new Set([operationId])
    let changed = true
    while (changed) {
      changed = false
      for (const x of a.queue)
        if (
          remove.has(x.dependsOn) ||
          a.queue.some((p) => remove.has(p.operation_id) && p.tempId === x.cardId)
        ) {
          if (!remove.has(x.operation_id)) {
            remove.add(x.operation_id)
            changed = true
          }
        }
    }
    a.queue = a.queue.filter((x) => !remove.has(x.operation_id))
    if (a.baseline) {
      a.caches = structuredClone(a.baseline)
      for (const op of a.queue) if (op.state !== 'blocked') optimistic(a, op)
    }
  })
  if (navigator.onLine) await pull(id)
  announce()
}
export async function retryOperation(operationId, useLocal = false) {
  const id = currentId()
  await changeAccount(id, (a) => {
    const op = a.queue.find((x) => x.operation_id === operationId)
    if (!op) return
    if (op.state === 'conflict' && !useLocal) throw new Error('Choose the server or local version.')
    if (op.state === 'blocked') throw new Error('Restore access before retrying.')
    if (useLocal) {
      op.revision = op.serverRevision
      const old = op.operation_id
      op.operation_id = crypto.randomUUID()
      for (const x of a.queue) if (x.dependsOn === old) x.dependsOn = op.operation_id
    }
    op.state = 'pending'
    op.error = ''
    op.nextRetry = 0
  })
  announce()
  await flush()
}
export async function prepareLogout(id = currentId()) {
  if (!id) return true
  const account = await readAccount(id)
  if (
    account.queue.length &&
    !window.confirm(
      `${account.queue.length} unsynchronized changes will be discarded on logout. Continue?`,
    )
  )
    return false
  // Acquire the replay lock so logout cannot race an in-flight mutation.
  const clear = () => clearAccount(id)
  if (navigator.locks) await navigator.locks.request(`ccapp-flush-${id}`, clear)
  else {
    if (running) await running
    await clear()
  }
  announce()
  return true
}
export function startSync() {
  window.addEventListener('online', () => void flush().catch(() => {}))
  window.addEventListener('offline', announce)
  setInterval(() => {
    if (navigator.onLine && currentId()) void flush().catch(() => {})
  }, 15000)
  useAuthStore.subscribe((state, previous) => {
    if (state.user?.id !== previous.user?.id && state.token) void flush().catch(() => {})
  })
  if (navigator.onLine) void flush().catch(() => {})
}

export async function refreshData() {
  if (navigator.onLine) await flush().catch(() => {})
  announce()
}

export async function resolveCardId(cardId) {
  const id = currentId()
  if (!id) return cardId
  const account = await readAccount(id)
  return account.mappings[cardId] ?? cardId
}
