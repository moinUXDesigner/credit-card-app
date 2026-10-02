import { useState } from 'react'
import client from '../api/client'
import { useCards } from '../hooks/useCards'
import { CATEGORIES } from '../schemas/cardSchema'
export default function ImportMessages() {
  const { cards } = useCards()
  const [card, setCard] = useState(''),
    [text, setText] = useState(''),
    [file, setFile] = useState(null),
    [preview, setPreview] = useState(null),
    [applySummary, setApplySummary] = useState(false),
    [error, setError] = useState(''),
    [notice, setNotice] = useState(''),
    [busy, setBusy] = useState(false)
  const update = (index, key, value) =>
    setPreview((p) => ({
      ...p,
      rows: p.rows.map((r, i) => (i === index ? { ...r, [key]: value } : r)),
    }))
  const parse = async (e) => {
    e.preventDefault()
    setError('')
    setNotice('')
    setBusy(true)
    try {
      const data = new FormData()
      if (file) data.append('file', file)
      else data.append('text', text)
      const r = await client.post(`/cards/${card}/messages/preview`, data)
      setPreview(r.data)
      setApplySummary(false)
      if (r.data.duplicate) setNotice('This message was already imported.')
    } catch (e) {
      setError(e.response?.data?.message ?? 'Unable to parse messages.')
    } finally {
      setBusy(false)
    }
  }
  const confirm = async (e) => {
    e.preventDefault()
    setError('')
    setBusy(true)
    try {
      const r = await client.post(`/cards/${card}/messages/confirm`, {
        preview_id: preview.preview_id,
        rows: preview.rows,
        apply_summary: applySummary,
      })
      setNotice(`Imported ${r.data.imported} transactions.`)
      setPreview(null)
      setText('')
      setFile(null)
    } catch (e) {
      setError(e.response?.data?.message ?? 'Review all fields and duplicate choices.')
    } finally {
      setBusy(false)
    }
  }
  return (
    <div className="space-y-4">
      <h1 className="text-xl font-semibold">Import SMS / email messages</h1>
      <p className="text-sm text-gray-600">
        Paste messages or upload a UTF-8 .txt/.eml file (up to 5 MB). Review the extracted data
        before saving. Message parsing requires an online connection.
      </p>
      {error && (
        <p role="alert" className="text-red-700">
          {error}
        </p>
      )}
      {notice && <p role="status">{notice}</p>}
      <form onSubmit={parse} className="space-y-3 rounded border bg-white p-4">
        <label className="block">
          Destination card
          <select
            required
            className="mt-1 block min-w-0 w-full rounded border p-2"
            value={card}
            onChange={(e) => {
              setCard(e.target.value)
              setPreview(null)
            }}
          >
            <option value="">Select card</option>
            {cards
              .filter((c) => c.permission !== 'viewer')
              .map((c) => (
                <option key={c.id} value={c.id}>
                  {c.card_name}
                </option>
              ))}
          </select>
        </label>
        <label className="block">
          Message text
          <textarea
            className="mt-1 block w-full rounded border p-2"
            rows={6}
            value={text}
            onChange={(e) => {
              setText(e.target.value)
              setPreview(null)
            }}
            placeholder="INR 500 spent at Grocery Store on 2026-10-01"
          />
        </label>
        <label className="block">
          Or upload a file
          <input
            type="file"
            accept=".txt,.eml"
            className="mt-1 block min-w-0 w-full"
            onChange={(e) => {
              setFile(e.target.files[0])
              setPreview(null)
            }}
          />
        </label>
        <button disabled={busy} className="rounded bg-indigo-600 px-4 py-2 text-white">
          {busy ? 'Processing…' : 'Preview messages'}
        </button>
      </form>
      {preview && !preview.duplicate && (
        <form onSubmit={confirm} className="space-y-3">
          <h2 className="font-semibold">Review extracted data</h2>
          <p className="text-sm">
            Confirm dates as YYYY-MM-DD. Possible duplicates require an explicit skip or keep
            choice; the server rechecks when saving.
          </p>
          {preview.rows.map((row, i) => (
            <fieldset key={i} className="grid min-w-0 gap-3 rounded border bg-white p-3 sm:grid-cols-2">
              <legend>Transaction {i + 1}</legend>
              {row.warnings?.length > 0 && (
                <p className="text-amber-700 sm:col-span-2">{row.warnings.join(' ')}</p>
              )}
              <label>
                Date
                <input
                  required
                  type="date"
                  className="mt-1 block min-w-0 w-full rounded border p-2"
                  value={row.transaction_date ?? ''}
                  onChange={(e) => update(i, 'transaction_date', e.target.value)}
                />
              </label>
              <label>
                Amount
                <input
                  required
                  type="number"
                  min="0.01"
                  step="0.01"
                  className="mt-1 block min-w-0 w-full rounded border p-2"
                  value={row.amount}
                  onChange={(e) => update(i, 'amount', e.target.value)}
                />
              </label>
              <label>
                Description
                <input
                  required
                  maxLength={255}
                  className="mt-1 block min-w-0 w-full rounded border p-2"
                  value={row.description}
                  onChange={(e) => update(i, 'description', e.target.value)}
                />
              </label>
              <label>
                Direction
                <select
                  required
                  className="mt-1 block min-w-0 w-full rounded border p-2"
                  value={row.direction ?? ''}
                  onChange={(e) => update(i, 'direction', e.target.value)}
                >
                  <option value="">Choose</option>
                  <option value="purchase">Purchase</option>
                  <option value="credit">Credit / payment</option>
                </select>
              </label>
              <label>
                Category
                <select
                  className="mt-1 block min-w-0 w-full rounded border p-2"
                  value={row.category}
                  onChange={(e) => update(i, 'category', e.target.value)}
                >
                  {CATEGORIES.map((c) => (
                    <option key={c}>{c}</option>
                  ))}
                </select>
              </label>
              <label>
                {row.possible_duplicate ? 'Possible duplicate' : 'Duplicate review'}
                <select
                  required={row.possible_duplicate}
                  className="mt-1 block min-w-0 w-full rounded border p-2"
                  value={row.duplicate_action ?? ''}
                  onChange={(e) => update(i, 'duplicate_action', e.target.value || null)}
                >
                  <option value="">Choose if needed</option>
                  <option value="skip">Skip</option>
                  <option value="keep">Keep as separate transaction</option>
                </select>
              </label>
            </fieldset>
          ))}
          {Object.keys(preview.summary).length > 0 && (
            <label className="block rounded border bg-amber-50 p-3">
              <input
                type="checkbox"
                checked={applySummary}
                onChange={(e) => setApplySummary(e.target.checked)}
              />{' '}
              Also update outstanding balance to ₹{preview.summary.current_outstanding}. Leave
              unchecked to keep the existing balance.
            </label>
          )}
          <button disabled={busy} className="rounded bg-indigo-600 px-4 py-2 text-white">
            Confirm import
          </button>
        </form>
      )}
    </div>
  )
}
