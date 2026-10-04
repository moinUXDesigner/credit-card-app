import StatementTransactions from './StatementTransactions'
import { useState } from 'react'
import Badge from '../common/Badge'
import Offcanvas from '../common/Offcanvas'
import { downloadStatement, getStatementViewUrl } from '../../api/statements'

const MONTH_NAMES = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
]

const STATUS_COLOR = { completed: 'green', pending: 'yellow', failed: 'red' }

export default function StatementListItem({ statement, onDelete, onReview, reviewing, readOnly, onCategoryChanged }) {
  const [expanded, setExpanded] = useState(false)
  const [pdfUrl, setPdfUrl] = useState(null)
  const [viewerOpen, setViewerOpen] = useState(false)
  const [error, setError] = useState(null)
  const [viewLoading, setViewLoading] = useState(false)

  const openViewer = async () => {
    setError(null)
    setViewerOpen(true)
    setViewLoading(true)
    try {
      const url = await getStatementViewUrl(statement.id)
      setPdfUrl(url)
    } catch (err) { setError(err.response?.data?.message ?? 'Could not open this PDF.'); setViewerOpen(false) } finally {
      setViewLoading(false)
    }
  }

  const closeViewer = () => {
    setViewerOpen(false)
    if (pdfUrl) {
      window.URL.revokeObjectURL(pdfUrl)
      setPdfUrl(null)
    }
  }

  return (
    <div className="rounded-lg border bg-white p-4 shadow-sm">
      <div className="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <span className="font-medium text-gray-900">
              {MONTH_NAMES[statement.billing_month - 1]} {statement.billing_year}
            </span>
            <Badge color={STATUS_COLOR[statement.analysis_status] ?? 'gray'}>{statement.analysis_status}</Badge>
          </div>
          <p className="mt-1 text-sm text-gray-600">
            {statement.original_filename}
            {statement.total_due !== null && ` · Total due ₹${statement.total_due.toLocaleString('en-IN')}`}
          </p>
          {statement.analysis_message && <p className="mt-1 text-xs text-amber-700">{statement.analysis_message}</p>}
        </div>
        <div className="flex shrink-0 flex-wrap gap-x-3 gap-y-2 text-sm">
          {!readOnly && ['pending', 'failed'].includes(statement.analysis_status) && <button disabled={statement.pending || reviewing || !navigator.onLine} onClick={() => onReview(statement)} className="text-indigo-600 hover:underline">{reviewing ? 'Analyzing…' : 'Review statement'}</button>}
          {statement.transactions.length > 0 && (
            <button onClick={() => setExpanded((v) => !v)} className="text-indigo-600 hover:underline">
              {expanded ? 'Hide' : `${statement.transactions.length} transactions`}
            </button>
          )}
          <button disabled={statement.pending} onClick={openViewer} className="text-indigo-600 hover:underline">
            View
          </button>
          <button
            disabled={statement.pending}
            onClick={() => downloadStatement(statement.id, statement.original_filename).catch(() => setError('Could not download this PDF.'))}
            className="text-indigo-600 hover:underline"
          >
            Download
          </button>
          {!readOnly && <button onClick={() => onDelete(statement.id)} className="text-red-600 hover:underline">
            Delete
          </button>}
        </div>
      </div>

      {error && <p role="alert" className="mt-2 text-sm text-red-700">{error}</p>}
      {expanded && <StatementTransactions statement={statement} readOnly={readOnly} onChanged={onCategoryChanged} />}

      <Offcanvas open={viewerOpen} onClose={closeViewer} title={statement.original_filename}>
        {viewLoading && <p className="p-4 text-sm text-gray-600">Loading statement…</p>}
        {!viewLoading && pdfUrl && (
          <iframe src={pdfUrl} title={statement.original_filename} className="h-full w-full" />
        )}
      </Offcanvas>
    </div>
  )
}
