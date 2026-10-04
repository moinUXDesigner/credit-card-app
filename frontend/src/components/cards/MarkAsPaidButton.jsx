import { useRef, useState } from 'react'
import { updateCard } from '../../api/cards'
import { refreshData } from '../../sync/engine'

const money = (amount) => `₹${Number(amount).toLocaleString('en-IN', { maximumFractionDigits: 2 })}`

export default function MarkAsPaidButton({ card }) {
  const dialog = useRef(null)
  const [mode, setMode] = useState('full')
  const [amount, setAmount] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)
  const outstanding = Math.round(Number(card.current_outstanding) * 100)
  const partial = /^\d+(?:\.\d{1,2})?$/.test(amount) ? Math.round(Number(amount) * 100) : NaN
  const paid = mode === 'full' ? outstanding : partial
  const valid = Number.isSafeInteger(paid) && paid > 0 && paid <= outstanding

  const open = () => {
    setMode('full'); setAmount(''); setError(null)
    dialog.current.showModal()
  }
  const submit = async (event) => {
    event.preventDefault()
    if (!valid || busy) return
    setBusy(true); setError(null)
    try {
      await updateCard(card.id, { current_outstanding: (outstanding - paid) / 100, revision: card.revision })
      dialog.current.close()
      await refreshData()
    } catch (err) {
      setError(err.response?.status === 409
        ? 'The balance changed. Close this dialog, refresh the card, and try again.'
        : err.response?.data?.message ?? 'Could not record the payment. Please try again.')
    } finally { setBusy(false) }
  }

  if (card.permission === 'viewer') return null
  return <>
    <button type="button" onClick={open} disabled={outstanding <= 0 || card.pending} className="rounded border border-indigo-600 px-3 py-1.5 text-indigo-600 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-50">Mark as paid</button>
    <dialog ref={dialog} aria-label="Mark as paid" onCancel={(event) => { if (busy) event.preventDefault() }} className="m-auto w-[calc(100%-2rem)] max-w-md rounded-xl border p-0 shadow-xl backdrop:bg-black/40">
      <form onSubmit={submit} className="space-y-4 p-5 text-sm">
        <h2 className="text-lg font-semibold text-gray-900">Mark as paid</h2>
        <p className="text-gray-600">{card.card_name} · •••• {card.last_four_digits}</p>
        <p>Current outstanding <strong>{money(outstanding / 100)}</strong></p>
        <p className="text-gray-500">Record a payment you have already made.</p>
        <fieldset className="space-y-3">
          <legend className="mb-2 font-medium">Payment amount</legend>
          <label className="flex items-center gap-2"><input type="radio" name="payment-mode" value="full" checked={mode === 'full'} onChange={() => setMode('full')} disabled={busy} />Full amount ({money(outstanding / 100)})</label>
          <label className="flex items-center gap-2"><input type="radio" name="payment-mode" value="partial" checked={mode === 'partial'} onChange={() => setMode('partial')} disabled={busy} />Part amount</label>
          {mode === 'partial' && <div>
            <label htmlFor={`payment-amount-${card.id}`} className="block text-gray-600">Amount paid (₹)</label>
            <input id={`payment-amount-${card.id}`} autoFocus type="number" inputMode="decimal" min="0.01" max={outstanding / 100} step="0.01" value={amount} onChange={(event) => setAmount(event.target.value)} disabled={busy} required className="mt-1 w-full rounded border px-3 py-2" />
            {amount && !valid && <p role="alert" className="mt-1 text-red-700">Enter an amount above zero and no greater than the outstanding balance, with up to two decimal places.</p>}
          </div>}
        </fieldset>
        <p className="rounded bg-indigo-50 p-3">Remaining outstanding <strong>{valid ? money((outstanding - paid) / 100) : '—'}</strong></p>
        {error && <p role="alert" className="text-red-700">{error}</p>}
        <div className="flex justify-end gap-3">
          <button type="button" disabled={busy} onClick={() => dialog.current.close()} className="rounded border px-3 py-2">Cancel</button>
          <button disabled={!valid || busy} className="rounded bg-indigo-600 px-3 py-2 text-white disabled:opacity-50">{busy ? 'Saving…' : 'Confirm payment'}</button>
        </div>
      </form>
    </dialog>
  </>
}
