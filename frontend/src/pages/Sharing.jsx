import { useCallback, useEffect, useState } from 'react'
import client from '../api/client'
import { useCards } from '../hooks/useCards'
export default function Sharing() {
  const { cards, refresh } = useCards()
  const [card, setCard] = useState(''),
    [members, setMembers] = useState([]),
    [invites, setInvites] = useState([]),
    [email, setEmail] = useState(''),
    [permission, setPermission] = useState('viewer'),
    [error, setError] = useState(''),
    [notice, setNotice] = useState('')
  const load = useCallback(async () => {
    try {
      setInvites((await client.get('/invitations')).data)
      setMembers(card ? (await client.get(`/cards/${card}/sharing`)).data : [])
    } catch (e) {
      setError(e.response?.data?.message ?? e.message)
    }
  }, [card])
  useEffect(() => {
    load()
  }, [load])
  const act = async (fn) => {
    setError('')
    try {
      await fn()
      await load()
      await refresh()
    } catch (e) {
      setError(e.response?.data?.message ?? e.message)
    }
  }
  return (
    <div className="space-y-4">
      <h1 className="text-xl font-semibold">Card sharing</h1>
      {error && (
        <p role="alert" className="text-red-700">
          {error}
        </p>
      )}
      {notice && <p role="status">{notice}</p>}
      <h2 className="font-medium">Invitations</h2>
      {invites.length === 0 && <p>No pending invitations.</p>}
      {invites.map((i) => (
        <div key={i.id} className="rounded border bg-white p-3">
          {i.card_name} · {i.permission} · expires {i.expires_at}
          <button
            className="ml-3 text-indigo-700"
            onClick={() => act(() => client.post(`/invitations/${i.id}/accept`))}
          >
            Accept full card access
          </button>
        </div>
      ))}
      <label className="block">
        Manage an owned card
        <select
          className="mt-1 block w-full min-w-0 max-w-full rounded border p-2 sm:w-auto"
          value={card}
          onChange={(e) => setCard(e.target.value)}
        >
          <option value="">Select card</option>
          {cards
            .filter((c) => c.permission === 'owner')
            .map((c) => (
              <option key={c.id} value={c.id}>
                {c.card_name}
              </option>
            ))}
        </select>
      </label>
      {card && (
        <>
          <p className="rounded border border-amber-200 bg-amber-50 p-3 text-sm">
            Inviting someone shares all card details, spend, transaction descriptions, and statement
            files. Viewers can read and download; editors can also change records. Only you can
            delete the card or manage sharing.
          </p>
          <form
            className="flex flex-col gap-2 sm:flex-row sm:flex-wrap"
            onSubmit={(e) => {
              e.preventDefault()
              act(async () => {
                await client.post(`/cards/${card}/sharing`, { email, permission })
                setEmail('')
                setNotice('Invitation sent. The recipient must accept.')
              })
            }}
          >
            <input
              aria-label="Recipient email"
              required
              type="email"
              placeholder="Existing user's email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="min-w-0 max-w-full rounded border p-2"
            />
            <select
              aria-label="Permission"
              className="min-w-0 max-w-full rounded border p-2"
              value={permission}
              onChange={(e) => setPermission(e.target.value)}
            >
              <option value="viewer">Viewer</option>
              <option value="editor">Editor</option>
            </select>
            <button className="rounded bg-indigo-600 px-3 py-2 text-white">
              Invite with full data access
            </button>
          </form>
          {members.map((m) => (
            <div
              className="flex flex-wrap items-center gap-3 rounded border bg-white p-3"
              key={m.id}
            >
              <span>
                {m.email} · {m.accepted_at ? 'Accepted' : 'Pending'}
              </span>
              <select
                aria-label={`Permission for ${m.email}`}
                value={m.permission}
                onChange={(e) =>
                  act(() =>
                    client.patch(`/cards/${card}/sharing/${m.id}`, { permission: e.target.value }),
                  )
                }
              >
                <option value="viewer">Viewer</option>
                <option value="editor">Editor</option>
              </select>
              <button
                className="text-red-700"
                onClick={() => act(() => client.delete(`/cards/${card}/sharing/${m.id}`))}
              >
                Revoke
              </button>
            </div>
          ))}
        </>
      )}
    </div>
  )
}
