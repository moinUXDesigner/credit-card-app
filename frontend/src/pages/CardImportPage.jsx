import { useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { importCards } from '../api/cards'

const SAMPLE_ROW = {
  card_name: 'Regalia',
  bank_name: 'HDFC',
  last_four_digits: '1234',
  network: 'visa',
  total_limit: 200000,
  shared_limit_group: '',
  current_outstanding: 35000,
  statement_day: 5,
  due_day: 25,
  annual_fee_amount: 2500,
  annual_fee_month: 9,
  waiver_spend_required: 300000,
  waiver_spend_completed: 0,
  card_year_start_month: 9,
  reward_point_balance: 0,
  reward_point_value_estimate: 0.25,
  best_categories: 'fuel;dining',
  reward_rate_general: 1.5,
  cashback_cap_amount: '',
  lounge_access: 'yes',
}

function downloadBlob(filename, content, type) {
  const url = URL.createObjectURL(new Blob([content], { type }))
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}

function downloadSampleCsv() {
  const headers = Object.keys(SAMPLE_ROW)
  const csv = [headers.join(','), headers.map((h) => SAMPLE_ROW[h]).join(',')].join('\n')
  downloadBlob('cards-template.csv', csv, 'text/csv')
}

function downloadSampleJson() {
  downloadBlob('cards-template.json', JSON.stringify([SAMPLE_ROW], null, 2), 'application/json')
}

export default function CardImportPage() {
  const [file, setFile] = useState(null)
  const [submitting, setSubmitting] = useState(false)
  const [result, setResult] = useState(null)
  const [error, setError] = useState(null)
  const fileInputRef = useRef(null)

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!file) return

    setSubmitting(true)
    setError(null)
    setResult(null)
    try {
      const data = await importCards(file)
      setResult(data)
      if (data.pending) setError('Import queued. Check Sync Center for processing results after reconnection.')
      setFile(null)
      if (fileInputRef.current) fileInputRef.current.value = ''
    } catch (err) {
      setError(err.response?.data?.message ?? 'Import failed.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
        <h1 className="text-lg font-semibold text-gray-900">Bulk Upload Cards</h1>
        <Link to="/cards" className="text-sm text-indigo-600 hover:underline">
          Back to My Cards
        </Link>
      </div>

      <div className="rounded-lg border bg-white p-4 shadow-sm sm:p-6">
        <p className="text-sm text-gray-600">
          Upload a CSV, Excel (.xlsx/.xls), ODS, or JSON file to add multiple cards at once. Each row/object needs
          the same fields as the "Add Card" form. <code>best_categories</code> can be a comma/semicolon/pipe
          separated list (e.g. <code>fuel;dining</code>), and boolean fields accept
          yes/no/true/false/1/0. Rows sharing a bank's combined limit should use the same{' '}
          <code>shared_limit_group</code> value and matching <code>total_limit</code>.
        </p>

        <div className="mt-3 flex flex-wrap gap-4 text-sm">
          <button type="button" onClick={downloadSampleCsv} className="text-indigo-600 hover:underline">
            Download sample CSV
          </button>
          <button type="button" onClick={downloadSampleJson} className="text-indigo-600 hover:underline">
            Download sample JSON
          </button>
        </div>

        <form onSubmit={handleSubmit} className="mt-6 space-y-4">
          <input
            ref={fileInputRef}
            type="file"
            accept=".csv,.json,.xlsx,.xls,.ods,.txt"
            onChange={(e) => setFile(e.target.files?.[0] ?? null)}
            className="block min-w-0 w-full text-sm text-gray-700"
          />
          <button
            type="submit"
            disabled={!file || submitting}
            className="rounded bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700 disabled:opacity-50"
          >
            {submitting ? 'Uploading…' : 'Upload'}
          </button>
        </form>

        {error && <p className="mt-4 rounded bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}

        {result && (
          <div className="mt-6 space-y-2">
            <p className="text-sm font-medium text-gray-900">
              Imported {result.imported} card{result.imported === 1 ? '' : 's'}
              {result.failed > 0 && `, ${result.failed} row${result.failed === 1 ? '' : 's'} failed`}.
            </p>
            {result.errors?.length > 0 && (
              <ul className="space-y-1 rounded border bg-red-50 p-3 text-sm text-red-700">
                {result.errors.map((e) => (
                  <li key={e.row}>
                    Row {e.row}: {e.errors.join(' ')}
                  </li>
                ))}
              </ul>
            )}
          </div>
        )}
      </div>
    </div>
  )
}
