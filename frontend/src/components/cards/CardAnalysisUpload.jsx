import { useRef, useState } from 'react'
import { analyzeCard } from '../../api/cardAnalysis'

export default function CardAnalysisUpload({ onAnalyzed }) {
  const [analyzing, setAnalyzing] = useState(false)
  const [error, setError] = useState(null)
  const [notice, setNotice] = useState(null)
  const fileInputRef = useRef(null)
  const cameraInputRef = useRef(null)

  const handleFile = async (file) => {
    if (!file) return
    setError(null)
    setNotice(null)
    setAnalyzing(true)
    try {
      const result = await analyzeCard(file).catch((err) => {
        if (file.type !== 'application/pdf') throw err
        return { document_recognized: false, card: {}, suggested_benefits: [], message: err.response?.data?.message ?? 'Card autofill was unavailable. Fill the card details manually and review the PDF.' }
      })
      if (result.document_recognized || file.type === 'application/pdf') {
        setNotice(result.message ?? 'Review the extracted fields. Missing values must be entered manually.')
        await onAnalyzed(result, file)
      } else {
        setNotice(result.message ?? "We couldn't recognize a credit card statement or card in this file.")
      }
    } catch (err) {
      setError(err.response?.data?.message ?? 'Failed to analyze this file. Please fill in the details manually.')
    } finally {
      setAnalyzing(false)
      if (fileInputRef.current) fileInputRef.current.value = ''
      if (cameraInputRef.current) cameraInputRef.current.value = ''
    }
  }

  return (
    <div className="space-y-2 rounded-lg border border-dashed bg-gray-50 p-4">
      <div className="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center gap-3">
        <div>
          <p className="text-sm font-medium text-gray-900">Upload a statement or card photo</p>
          <p className="text-xs text-gray-500">
            We'll try to auto-fill this form from your statement PDF or a photo of your card. Anything we can't
            read, you can fill in yourself. A statement PDF can be reviewed and saved as your first statement. Card photos are used only for autofill.
          </p>
        </div>
        <div className="flex shrink-0 flex-wrap gap-2">
          <button
            type="button"
            disabled={analyzing}
            onClick={() => fileInputRef.current?.click()}
            className="rounded border border-indigo-600 px-3 py-1.5 text-sm text-indigo-600 hover:bg-indigo-50 disabled:opacity-50"
          >
            {analyzing ? 'Analyzing…' : 'Upload file'}
          </button>
          <button
            type="button"
            disabled={analyzing}
            onClick={() => cameraInputRef.current?.click()}
            className="rounded border border-indigo-600 px-3 py-1.5 text-sm text-indigo-600 hover:bg-indigo-50 disabled:opacity-50"
          >
            Take a photo
          </button>
        </div>
      </div>

      <input
        ref={fileInputRef}
        type="file"
        accept="application/pdf,image/*"
        className="hidden"
        onChange={(e) => handleFile(e.target.files?.[0])}
      />
      <input
        ref={cameraInputRef}
        type="file"
        accept="image/*"
        capture="environment"
        className="hidden"
        onChange={(e) => handleFile(e.target.files?.[0])}
      />

      {error && <p className="rounded bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}
      {notice && <p className="rounded bg-amber-50 px-3 py-2 text-sm text-amber-700">{notice}</p>}
    </div>
  )
}
