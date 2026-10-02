import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuthStore } from '../../store/authStore'
import { readAccount } from '../../sync/storage'
export default function SyncStatus() {
  const id = useAuthStore((s) => s.user?.id),
    [account, setAccount] = useState(null),
    [online, setOnline] = useState(navigator.onLine)
  useEffect(() => {
    const load = () => {
      setOnline(navigator.onLine)
      if (id)
        readAccount(id)
          .then(setAccount)
          .catch(() => {})
    }
    load()
    for (const event of ['sync-change', 'online', 'offline']) window.addEventListener(event, load)
    return () => {
      for (const event of ['sync-change', 'online', 'offline'])
        window.removeEventListener(event, load)
    }
  }, [id])
  if (!id) return null
  return (
    <div role="status" className="mb-4 rounded border bg-white px-3 py-2 text-sm text-gray-600">
      {online ? 'Online' : 'Offline'} ·{' '}
      {account?.enabled
        ? `${account.queue.length} unsynchronized changes`
        : 'Offline storage disabled'}
      {account?.queue.some(
        (x) => x.state === 'conflict' || x.state === 'blocked' || x.state === 'failed',
      ) && ' · Review required'}{' '}
      <Link to="/sync" className="ml-2 text-indigo-700">
        Sync Center
      </Link>
    </div>
  )
}
