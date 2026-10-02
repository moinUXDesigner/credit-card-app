import { useCallback, useEffect, useState } from 'react'
import { useAuthStore } from '../store/authStore'
import { readAccount, clearAccount } from '../sync/storage'
import { enableOffline, flush, discardOperation, retryOperation } from '../sync/engine'
export default function SyncCenter() {
  const id = useAuthStore((s) => s.user?.id)
  const [account, setAccount] = useState(null),
    [error, setError] = useState(''),
    [busy, setBusy] = useState(false)
  const load = useCallback(async () => {
    try {
      if (id) setAccount(await readAccount(id))
    } catch (e) {
      setError(e.message)
    }
  }, [id])
  useEffect(() => {
    load()
    window.addEventListener('sync-change', load)
    return () => window.removeEventListener('sync-change', load)
  }, [load])
  const act = async (fn) => {
    setBusy(true)
    setError('')
    try {
      await fn()
      await load()
    } catch (e) {
      setError(e.message)
    } finally {
      setBusy(false)
    }
  }
  return (
    <div className="space-y-4">
      <h1 className="text-xl font-semibold">Sync Center</h1>
      <p>
        Connection: {navigator.onLine ? 'Online' : 'Offline'} · Last sync:{' '}
        {account?.lastSync ?? 'Never'}
      </p>
      {error && (
        <p role="alert" className="text-red-700">
          {error}
        </p>
      )}
      {account?.paused && (
        <p role="alert" className="text-amber-700">
          {account.paused}
        </p>
      )}
      {!account?.enabled ? (
        <div className="space-y-3 rounded border bg-white p-4">
          <p>
            Enable offline storage on this device to cache accessible card data and queue changes,
            imports, and PDF uploads. Previously cached shared data cannot be remotely removed while
            you are offline; it is purged on reconnection. Use a trusted device.
          </p>
          <button
            disabled={busy}
            className="rounded bg-indigo-600 px-4 py-2 text-white"
            onClick={() => act(enableOffline)}
          >
            Enable offline storage
          </button>
        </div>
      ) : (
        <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
          <button
            disabled={busy}
            className="rounded bg-indigo-600 px-4 py-2 text-white"
            onClick={() => act(flush)}
          >
            Sync now
          </button>
          <button
            disabled={busy}
            className="rounded border px-4 py-2"
            onClick={() =>
              act(async () => {
                if (
                  account.queue.length &&
                  !window.confirm('Discard all unsynchronized changes and disable offline storage?')
                )
                  return
                await clearAccount(id)
              })
            }
          >
            Disable and clear device storage
          </button>
        </div>
      )}
      <p className="text-sm text-gray-600">
        Pending files:{' '}
        {((account?.queue.reduce((n, o) => n + (o.file?.size ?? 0), 0) ?? 0) / 1024 / 1024).toFixed(
          1,
        )}{' '}
        / 100 MB. AI, admin, invitations, and message parsing require a connection. Reports may show
        the last downloaded values while offline.
      </p>
      <h2 className="font-medium">Unsynchronized changes ({account?.queue.length ?? 0})</h2>
      {!account?.queue.length && <p>No unsynchronized changes.</p>}
      {account?.queue.map((op) => (
        <article key={op.operation_id} className="space-y-2 rounded border bg-white p-4">
          <h3 className="font-medium">
            {op.method} {op.path} · {op.state}
          </h3>
          <p className="text-xs text-gray-500">
            {op.createdAt}
            {op.filename && ` · ${op.filename}`}
          </p>
          {op.error && (
            <p role="alert" className="text-amber-700">
              {op.error}
            </p>
          )}
          {op.dependsOn && <p className="text-sm">Waiting for an earlier change to this record.</p>}
          {op.state === 'conflict' && (
            <>
              <div className="grid gap-3 md:grid-cols-2">
                <div>
                  <h4 className="text-sm font-medium">Your pending changes</h4>
                  <pre className="max-h-60 min-w-0 overflow-auto whitespace-pre-wrap break-all text-xs">
                    {JSON.stringify(op.payload, null, 2)}
                  </pre>
                </div>
                <div>
                  <h4 className="text-sm font-medium">Server record</h4>
                  <pre className="max-h-60 min-w-0 overflow-auto whitespace-pre-wrap break-all text-xs">
                    {JSON.stringify(op.server, null, 2)}
                  </pre>
                </div>
              </div>
              <button
                disabled={busy}
                className="mr-3 text-indigo-700"
                onClick={() => act(() => retryOperation(op.operation_id, true))}
              >
                Apply my version to latest record
              </button>
            </>
          )}
          {op.state !== 'conflict' && op.state !== 'blocked' && (
            <button
              disabled={busy}
              className="mr-3 text-indigo-700"
              onClick={() => act(() => retryOperation(op.operation_id))}
            >
              Retry
            </button>
          )}
          <button
            disabled={busy}
            className="text-red-700"
            onClick={() =>
              act(async () => {
                if (window.confirm('Discard this change and any dependent changes?'))
                  await discardOperation(op.operation_id)
              })
            }
          >
            {op.state === 'conflict' ? 'Use server version' : 'Discard'}
          </button>
        </article>
      ))}
      <h2 className="font-medium">Recent processing results</h2>
      <ul className="rounded border bg-white p-3">
        {account?.history?.map((item) => (
          <li key={item.operation_id} className="border-b py-2 text-sm">
            {item.finished} · {item.summary}
          </li>
        ))}
      </ul>
    </div>
  )
}
