import { useRef, useState } from 'react'
import { uploadStatement } from '../../api/statements'

const MONTHS = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
]

export default function StatementUploadForm({ cardId, onUploaded }) {
  const now = new Date()
  const [billingMonth, setBillingMonth] = useState(now.getMonth() + 1)
  const [billingYear, setBillingYear] = useState(now.getFullYear())
  const [uploading, setUploading] = useState(false)
  const [error, setError] = useState(null)
  const fileInputRef = useRef(null)

  const handleFile = async (file) => {
    if (!file) return
    setError(null)
    setUploading(true)
    try {
      await uploadStatement(cardId, file, billingMonth, billingYear)
      onUploaded()
    } catch (err) {
      setError(err.response?.data?.message ?? 'Failed to upload this statement.')
    } finally {
      setUploading(false)
      if (fileInputRef.current) fileInputRef.current.value = ''
    }
  }

  return (
    <div className="space-y-2 rounded-lg border border-dashed bg-gray-50 p-4">
      <div className="flex flex-wrap items-center gap-3">
        <div>
          <label className="block text-xs font-medium text-gray-700">Billing month</label>
          <select
            value={billingMonth}
            onChange={(e) => setBillingMonth(Number(e.target.value))}
            disabled={uploading}
            className="mt-1 rounded border px-2 py-1.5 text-sm"
          >
            {MONTHS.map((name, i) => (
              <option key={name} value={i + 1}>
                {name}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-700">Billing year</label>
          <input
            type="number"
            value={billingYear}
            onChange={(e) => setBillingYear(Number(e.target.value))}
            disabled={uploading}
            className="mt-1 w-24 rounded border px-2 py-1.5 text-sm"
          />
        </div>
        <button
          type="button"
          disabled={uploading}
          onClick={() => fileInputRef.current?.click()}
          className="mt-5 rounded border border-indigo-600 px-3 py-1.5 text-sm text-indigo-600 hover:bg-indigo-50 disabled:opacity-50"
        >
          {uploading ? 'Analyzing statement…' : 'Upload statement PDF'}
        </button>
      </div>
      <p className="text-xs text-gray-500">
        We'll extract transactions (categorized automatically) and refresh this card's outstanding balance and
        waiver progress. This can take up to a minute.
      </p>

      <input
        ref={fileInputRef}
        type="file"
        accept="application/pdf"
        className="hidden"
        onChange={(e) => handleFile(e.target.files?.[0])}
      />

      {error && <p className="rounded bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}
    </div>
  )
}
