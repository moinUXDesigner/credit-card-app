import { useCallback, useEffect, useState } from 'react'
import client from '../api/client'
import { useAuthStore } from '../store/authStore'
export default function Admin() {
  const user = useAuthStore((s) => s.user)
  const [users, setUsers] = useState(null),
    [audit, setAudit] = useState(null),
    [health, setHealth] = useState(null)
  const [search, setSearch] = useState(''),
    [submittedSearch, setSubmittedSearch] = useState(''),
    [page, setPage] = useState(1),
    [error, setError] = useState('')
  const load = useCallback(async () => {
    try {
      const [u, a, h] = await Promise.all([
        client.get('/admin/users', { params: { search: submittedSearch, page } }),
        client.get('/admin/audit'),
        client.get('/admin/health'),
      ])
      setUsers(u.data)
      setAudit(a.data)
      setHealth(h.data)
    } catch (e) {
      setError(e.response?.data?.message ?? 'Unable to load admin data.')
    }
  }, [submittedSearch, page])
  useEffect(() => {
    if (user?.role === 'admin') load()
  }, [user?.role, load])
  if (user?.role !== 'admin') return <p role="alert">Admin access is required.</p>
  return (
    <div className="space-y-5">
      <h1 className="text-xl font-semibold">Admin operations</h1>
      <p className="text-sm text-gray-600">
        Account operations only. Personal card and statement data is not available here.
      </p>
      {error && (
        <p role="alert" className="text-red-700">
          {error}
        </p>
      )}
      <form
        onSubmit={(e) => {
          e.preventDefault()
          setPage(1)
          setSubmittedSearch(search)
        }}
        className="flex flex-col gap-2 sm:flex-row"
      >
        <input
          aria-label="Search users"
          className="min-w-0 rounded border p-2"
          placeholder="Name or email"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
        <button className="rounded bg-indigo-600 px-3 py-2 text-white">Search</button>
      </form>
      {health && (
        <div className="rounded border bg-white p-4">
          <h2 className="font-medium">System health</h2>
          <p>
            Database: {health.database} · AI configured: {health.ai_configured ? 'yes' : 'no'} ·
            Storage writable: {health.storage_writable ? 'yes' : 'no'}
          </p>
        </div>
      )}
      <div className="overflow-auto rounded border bg-white">
        <table className="min-w-[32rem] w-full text-left text-sm">
          <thead>
            <tr>
              {['User', 'Role', 'Status', 'Action'].map((x) => (
                <th key={x} className="p-3">
                  {x}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {users?.data.map((u) => (
              <tr key={u.id} className="border-t">
                <td className="p-3">
                  {u.name}
                  <br />
                  {u.email}
                </td>
                <td>{u.role}</td>
                <td>{u.suspended_at ? 'Suspended' : 'Active'}</td>
                <td>
                  <button
                    disabled={u.id === user.id}
                    className="p-2 text-indigo-700 disabled:text-gray-400"
                    onClick={async () => {
                      try {
                        await client.patch(`/admin/users/${u.id}/status`, {
                          suspended: !u.suspended_at,
                        })
                        await load()
                      } catch (e) {
                        setError(e.response?.data?.message)
                      }
                    }}
                  >
                    {u.suspended_at ? 'Reactivate' : 'Suspend'}
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="flex flex-wrap gap-3">
        <button disabled={page === 1} onClick={() => setPage(page - 1)}>
          Previous
        </button>
        <span>
          Page {page} of {users?.last_page ?? 1}
        </span>
        <button disabled={!users || page >= users.last_page} onClick={() => setPage(page + 1)}>
          Next
        </button>
      </div>
      <h2 className="font-semibold">Audit history</h2>
      <ul className="rounded border bg-white p-4">
        {audit?.data.map((a) => (
          <li key={a.id} className="border-b py-2 text-sm">
            {a.created_at}: {a.action} · actor {a.actor_id ?? 'CLI'} · account {a.target_id}
          </li>
        ))}
      </ul>
      <button
        disabled={!audit?.next_page_url}
        onClick={async () => {
          try {
            const r = await client.get('/admin/audit', { params: { page: audit.current_page + 1 } })
            setAudit(r.data)
          } catch (e) {
            setError(e.message)
          }
        }}
      >
        Older audit events
      </button>
    </div>
  )
}
