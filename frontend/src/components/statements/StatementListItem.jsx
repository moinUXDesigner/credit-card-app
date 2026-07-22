import { useState } from 'react'
import Badge from '../common/Badge'
import Offcanvas from '../common/Offcanvas'
import { downloadStatement, getStatementViewUrl } from '../../api/statements'

const MONTH_NAMES = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
]

const STATUS_COLOR = { completed: 'green', pending: 'yellow', failed: 'red' }

export default function StatementListItem({ statement, onDelete }) {
  const [expanded, setExpanded] = useState(false)
  const [pdfUrl, setPdfUrl] = useState(null)
  const [viewerOpen, setViewerOpen] = useState(false)
  const [viewLoading, setViewLoading] = useState(false)

  const openViewer = async () => {
    setViewerOpen(true)
    setViewLoading(true)
    try {
      const url = await getStatementViewUrl(statement.id)
      setPdfUrl(url)
    } finally {
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
      <div className="flex items-center justify-between">
        <div>
          <div className="flex items-center gap-2">
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
        <div className="flex gap-3 text-sm">
          {statement.transactions.length > 0 && (
            <button onClick={() => setExpanded((v) => !v)} className="text-indigo-600 hover:underline">
              {expanded ? 'Hide' : `${statement.transactions.length} transactions`}
            </button>
          )}
          <button onClick={openViewer} className="text-indigo-600 hover:underline">
            View
          </button>
          <button
            onClick={() => downloadStatement(statement.id, statement.original_filename)}
            className="text-indigo-600 hover:underline"
          >
            Download
          </button>
          <button onClick={() => onDelete(statement.id)} className="text-red-600 hover:underline">
            Delete
          </button>
        </div>
      </div>

      {expanded && (
        <div className="mt-3 space-y-1 border-t pt-3">
          {statement.transactions.map((t) => (
            <div key={t.id} className="flex items-center justify-between text-sm">
              <span className="text-gray-700">
                {t.transaction_date} · {t.description}
              </span>
              <span className="flex items-center gap-2">
                {t.category && <Badge color="indigo">{t.category}</Badge>}
                <span className="text-gray-900">₹{t.amount.toLocaleString('en-IN')}</span>
              </span>
            </div>
          ))}
        </div>
      )}

      <Offcanvas open={viewerOpen} onClose={closeViewer} title={statement.original_filename}>
        {viewLoading && <p className="p-4 text-sm text-gray-600">Loading statement…</p>}
        {!viewLoading && pdfUrl && (
          <iframe src={pdfUrl} title={statement.original_filename} className="h-full w-full" />
        )}
      </Offcanvas>
    </div>
  )
}
