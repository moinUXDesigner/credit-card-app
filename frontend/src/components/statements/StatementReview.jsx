import { useEffect, useRef, useState } from 'react'
import { CATEGORIES } from '../../schemas/cardSchema'

const summaryFields = [
  ['statement_date', 'Statement date', 'date'], ['due_date', 'Due date', 'date'],
  ['total_due', 'Total outstanding / amount due (₹)', 'number'], ['minimum_due', 'Minimum due (₹)', 'number'],
  ['credit_limit', 'Credit limit (₹)', 'number'], ['reward_point_balance', 'Reward points', 'number'],
]
const editableSummary = (summary) => Object.fromEntries(summaryFields.map(([key]) => [key, summary[key] ?? '']))
export default function StatementReview({ preview, card, onConfirm, onCancel, confirmLabel = 'Confirm import', deferImport = false, onReviewChange }) {
  const date = preview.summary?.statement_date
  const [month, setMonth] = useState(date ? Number(date.slice(5, 7)) : new Date().getMonth() + 1)
  const [year, setYear] = useState(date ? Number(date.slice(0, 4)) : new Date().getFullYear())
  const [summary, setSummary] = useState(editableSummary(preview.summary ?? {}))
  const [rows, setRows] = useState((preview.rows ?? []).map((r) => ({ ...r, duplicate_action: r.possible_duplicate ? '' : 'keep' })))
  const [apply, setApply] = useState(preview.apply_defaults ?? {})
  const [acknowledge, setAcknowledge] = useState(false)
  const [pdfOnly, setPdfOnly] = useState(!preview.analyzed)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)
  const [operationId] = useState(() => crypto.randomUUID())
  const changeCallback = useRef(onReviewChange)
  changeCallback.current = onReviewChange
  useEffect(() => { changeCallback.current?.() }, [summary, rows, apply, acknowledge, pdfOnly, month, year])
  const identity = preview.summary?.last_four_digits
  const mismatch = identity && card?.last_four_digits && identity !== card.last_four_digits
  const fieldClass = 'mt-1 w-full min-w-0 rounded border px-2 py-1.5 text-sm'
  const setRow = (i, key, value) => setRows((old) => old.map((r, index) => index === i ? { ...r, [key]: value } : r))
  const submit = async (event) => {
    event.preventDefault()
    setError(null)
    setBusy(true)
    try {
      const reviewed = Object.fromEntries(summaryFields.map(([key, , type]) => [key,
        summary[key] === '' ? null : type === 'number' ? Number(summary[key]) : summary[key],
      ]))
      await onConfirm({ preview_id: preview.preview_id, idempotency_key: operationId,
        revision: preview.card_revision ?? card?.revision, billing_month: Number(month), billing_year: Number(year),
        summary: reviewed, rows: pdfOnly ? [] : rows.filter((r) => r.duplicate_action !== 'skip').map(({ possible_duplicate: _duplicate, ...row }) => ({ ...row, amount: Number(row.amount) })),
        apply_summary: pdfOnly ? {} : apply, acknowledge_identity: Boolean(identity) || acknowledge, save_pdf_only: pdfOnly,
      })
    } catch (err) {
      setError(err.response?.data?.message ?? err.message ?? 'Could not import this statement.')
    } finally { setBusy(false) }
  }
  return (
    <form onSubmit={submit} className="space-y-4 rounded-lg border bg-white p-4 shadow-sm">
      <h2 className="font-semibold">Review statement</h2>
      <p className="break-words text-sm">{preview.original_filename} · Destination: {card ? `${card.bank_name} ${card.card_name} •••• ${card.last_four_digits}` : 'The new card you are adding'}</p>
      <p className="text-sm">Detected card: {identity ? `•••• ${identity}` : 'Last four digits could not be read'}</p>
      {mismatch && <p role="alert" className="text-sm text-red-700">This statement belongs to a different card. Upload the correct PDF.</p>}
      {preview.message && <p className="text-sm text-amber-700">{preview.message}</p>}
      {(preview.warnings ?? []).map((w, i) => <p key={i} className="text-sm text-amber-700">{w}</p>)}
      {preview.duplicate_statement_id && <p className="text-sm text-amber-700">This PDF was already saved. Confirmation returns the existing statement without importing again.</p>}
      {!identity && <label className="flex gap-2 text-sm"><input type="checkbox" checked={acknowledge} onChange={(e) => setAcknowledge(e.target.checked)} />I confirm this PDF belongs to the destination card.</label>}
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <label className="text-sm">Billing month<input className={fieldClass} type="number" min="1" max="12" required value={month} onChange={(e) => setMonth(e.target.value)} /></label>
        <label className="text-sm">Billing year<input className={fieldClass} type="number" min="2000" max="2100" required value={year} onChange={(e) => setYear(e.target.value)} /></label>
        {summaryFields.map(([key, label, type]) => <label key={key} className="text-sm">{label}<input className={fieldClass} type={type} min={type === 'number' ? 0 : undefined} step={type === 'number' ? '0.01' : undefined} value={summary[key]} onChange={(e) => setSummary({ ...summary, [key]: e.target.value })} /></label>)}
      </div>
      <label className="flex gap-2 text-sm"><input type="checkbox" checked={pdfOnly} onChange={(e) => setPdfOnly(e.target.checked)} />Save PDF only, without changing balances or spending.</label>
      {!pdfOnly && <>
        <div className="space-y-2">
          {[['current_outstanding', 'Update outstanding balance'], ['reward_point_balance', 'Update reward balance'], ['total_limit', 'Update credit limit']].map(([key, label]) => <label key={key} className="flex gap-2 text-sm"><input type="checkbox" checked={Boolean(apply[key])} onChange={(e) => setApply({ ...apply, [key]: e.target.checked })} />{label}</label>)}
          <p className="text-xs text-gray-500">Historical statements do not replace newer balances. Limit changes must match any shared limit group.</p>
        </div>
        <h3 className="text-sm font-semibold">Transactions ({rows.length})</h3>
        {rows.length === 0 && <p className="text-sm text-amber-700">No transactions extracted. Check the PDF before confirming.</p>}
        <div className="max-h-96 space-y-3 overflow-y-auto">
          {rows.map((row, i) => <fieldset key={i} className="rounded border p-3">
            <legend className="text-xs">Transaction {i + 1}{row.possible_duplicate ? ' · Possible duplicate — choose skip or keep' : ''}</legend>
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
              <label className="text-xs">Date<input required={row.duplicate_action !== 'skip'} type="date" className={fieldClass} value={row.transaction_date} onChange={(e) => setRow(i, 'transaction_date', e.target.value)} /></label>
              <label className="text-xs">Description<input required={row.duplicate_action !== 'skip'} maxLength={255} className={fieldClass} value={row.description} onChange={(e) => setRow(i, 'description', e.target.value)} /></label>
              <label className="text-xs">Amount (₹)<input required={row.duplicate_action !== 'skip'} type="number" min="0.01" step="0.01" className={fieldClass} value={row.amount} onChange={(e) => setRow(i, 'amount', e.target.value)} /></label>
              <label className="text-xs">Category<select className={fieldClass} value={row.category} onChange={(e) => setRow(i, 'category', e.target.value)}>{CATEGORIES.map((c) => <option key={c}>{c}</option>)}</select></label>
              <label className="text-xs">Direction<select className={fieldClass} value={row.direction} onChange={(e) => setRow(i, 'direction', e.target.value)}><option value="purchase">Purchase / charge</option><option value="credit">Refund / credit</option></select></label>
              <label className="text-xs">Import action<select required className={fieldClass} value={row.duplicate_action} onChange={(e) => setRow(i, 'duplicate_action', e.target.value)}><option value="">Choose…</option><option value="keep">Keep</option><option value="skip">Skip</option></select></label>
            </div>
          </fieldset>)}
        </div>
      </>}
      {error && <p role="alert" className="text-sm text-red-700">{error}</p>}
      <div className="flex flex-wrap gap-3">
        <button disabled={busy || mismatch || (!identity && !acknowledge) || (!preview.analyzed && !pdfOnly)} className="rounded bg-indigo-600 px-3 py-2 text-sm text-white disabled:opacity-50">{busy ? 'Saving…' : confirmLabel}</button>
        <button disabled={busy} type="button" onClick={onCancel} className="rounded border px-3 py-2 text-sm">Cancel review</button>
      </div>
      {deferImport && <p className="text-xs text-gray-500">The PDF will be attached after the card is created. No data has been imported yet.</p>}
    </form>
  )
}
