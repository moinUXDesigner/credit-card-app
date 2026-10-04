import { useState } from 'react'
import Badge from '../common/Badge'
import { CATEGORIES } from '../../schemas/cardSchema'
import { updateTransactionCategory } from '../../api/statements'

const label = (value) => value === 'medicines' ? 'Medicine' : value === 'online_food' ? 'Online food' : value.charAt(0).toUpperCase() + value.slice(1)

export default function StatementTransactions({ statement, readOnly, onChanged }) {
  const [editing, setEditing] = useState(null)
  const [category, setCategory] = useState('other')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)
  const [saved, setSaved] = useState({})
  const start = (row) => { setEditing(row.id); setCategory(saved[row.id] ?? row.category ?? 'other'); setError(null) }
  const save = async (row) => {
    setBusy(true); setError(null)
    try {
      const result = await updateTransactionCategory(statement.id, row.id, category)
      setSaved((previous) => ({ ...previous, [row.id]: result.category }))
      setEditing(null)
      await onChanged?.()
    } catch (err) { setError(err.response?.data?.message ?? 'Could not update this category. Please try again.') }
    finally { setBusy(false) }
  }
  return <div className="mt-4 border-t pt-4">
    {error && <p role="alert" className="mb-3 rounded bg-red-50 p-2 text-sm text-red-700">{error}</p>}
    <div className="overflow-x-auto rounded-lg border">
      <table className="w-full min-w-[640px] text-left text-sm">
        <caption className="sr-only">Statement transactions and spend categories</caption>
        <thead className="border-b bg-gray-50 text-xs uppercase tracking-wide text-gray-500"><tr>
          <th scope="col" className="px-4 py-3">Date</th><th scope="col" className="px-4 py-3">Description</th>
          <th scope="col" className="w-52 px-4 py-3">Category</th><th scope="col" className="px-4 py-3 text-right">Amount</th>
        </tr></thead>
        <tbody className="divide-y divide-gray-100">{statement.transactions.map((row) => <tr key={row.id} className="hover:bg-gray-50/70">
          <td className="whitespace-nowrap px-4 py-3 text-gray-500">{row.transaction_date}</td>
          <td className="min-w-52 px-4 py-3 font-medium text-gray-800">{row.description}</td>
          <td className="px-4 py-3">
            {editing === row.id ? <div className="space-y-2">
              <select aria-label={`Category for ${row.description}`} autoFocus disabled={busy} value={category} onChange={(event) => setCategory(event.target.value)} className="w-full rounded border border-indigo-300 bg-white px-2 py-1.5 text-sm focus:outline-indigo-600">
                {CATEGORIES.map((option) => <option key={option} value={option}>{label(option)}</option>)}
              </select>
              <div className="flex gap-3 text-xs"><button type="button" disabled={busy} onClick={() => save(row)} className="font-medium text-indigo-600">{busy ? 'Saving…' : 'Save'}</button><button type="button" disabled={busy} onClick={() => { setEditing(null); setError(null) }} className="text-gray-500">Cancel</button></div>
            </div> : readOnly ? <Badge color="indigo">{label(saved[row.id] ?? row.category ?? 'other')}</Badge> : <button type="button" disabled={busy || statement.pending} onClick={() => start(row)} aria-label={`Edit category for ${row.description}`} className="inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 hover:border-indigo-300 hover:bg-indigo-100 focus-visible:outline-indigo-600">
              {label(saved[row.id] ?? row.category ?? 'other')}<span aria-hidden="true">✎</span>
            </button>}
          </td>
          <td className="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums text-gray-900">₹{row.amount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
        </tr>)}</tbody>
      </table>
    </div>
  </div>
}
