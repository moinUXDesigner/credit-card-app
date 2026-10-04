import { useRef, useState } from 'react'
import { previewStatement, uploadStatement, confirmStatement } from '../../api/statements'
import StatementReview from './StatementReview'

export default function StatementUploadForm({ card, onUploaded, initialPreview, onReviewClosed }) {
  const [preview, setPreview] = useState(initialPreview ?? null)
  const [uploading, setUploading] = useState(false)
  const [error, setError] = useState(null)
  const [notice, setNotice] = useState(null)
  const fileInputRef = useRef(null)
  const retainedFile = useRef(null)
  const handleFile = async (file) => {
    if (!file) return
    setError(null); setNotice(null); setUploading(true)
    try {
      if (file.size > 15 * 1024 * 1024) throw new Error('PDFs must be no larger than 15 MB.')
      if (!navigator.onLine || String(card.id).startsWith('local-')) {
        await uploadStatement(card.id, file, new Date().getMonth() + 1, new Date().getFullYear())
        setNotice('PDF queued. After sync, choose Review to analyze and confirm it. No balance or spending changes have been applied.')
        await onUploaded()
      } else { retainedFile.current = file; setPreview(await previewStatement(file, card.id)) }
    } catch (err) { setError(err.response?.data?.message ?? err.message ?? 'Could not analyze this PDF. If it is password protected, upload an unlocked copy.') }
    finally { setUploading(false); if (fileInputRef.current) fileInputRef.current.value = '' }
  }
  const close = () => { setPreview(null); onReviewClosed?.() }
  const confirm = async (payload) => {
    const result = await confirmStatement(card.id, payload, retainedFile.current)
    setNotice(result.pending ? 'Import queued for sync. The server will recheck access and card changes.' : result.requires_review ? 'The preview expired. Your PDF is saved; review it again before importing.' : 'Statement saved.')
    close()
    await onUploaded()
  }
  return <div className="space-y-3">
    <div className="space-y-2 rounded-lg border border-dashed bg-gray-50 p-4">
      <button type="button" disabled={uploading} onClick={() => fileInputRef.current?.click()} className="rounded border border-indigo-600 px-3 py-2 text-sm text-indigo-600 disabled:opacity-50">{uploading ? 'Analyzing statement…' : 'Upload statement PDF'}</button>
      <p className="text-xs text-gray-500">Review dates, balances, and transactions before importing. PDF files up to 15 MB. For password-protected statements, upload an unlocked copy.</p>
      <input ref={fileInputRef} type="file" accept="application/pdf" className="hidden" onChange={(e) => handleFile(e.target.files?.[0])} />
      {error && <p role="alert" className="text-sm text-red-700">{error}</p>}
      {notice && <p role="status" className="text-sm text-gray-700">{notice}</p>}
    </div>
    {preview && <StatementReview key={preview.preview_id} preview={preview} card={card} onConfirm={confirm} onCancel={close} />}
  </div>
}
