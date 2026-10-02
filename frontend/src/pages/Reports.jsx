import { useEffect, useState } from 'react'
import { useCards } from '../hooks/useCards'
import { getMonthlyReport, logMonthlySpend } from '../api/reports'
import Skeleton from '../components/common/Skeleton'

const MONTH_NAMES = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
]

function LogSpendRow({ card, year, month, onSaved }) {
  const [amount, setAmount] = useState('')
  const [saving, setSaving] = useState(false)

  const handleSave = async () => {
    setSaving(true)
    try {
      await logMonthlySpend(card.id, { year, month, amount_spent: Number(amount) || 0 })
      onSaved()
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center border-b py-2 last:border-0">
      <span className="text-sm text-gray-700">{card.card_name}</span>
      <div className="flex w-full items-center gap-2 sm:w-auto">
        <input
          type="number"
          step="0.01"
          placeholder="Amount spent"
          value={amount}
          onChange={(e) => setAmount(e.target.value)}
          className="min-w-0 w-full rounded border sm:w-32 px-2 py-1 text-sm"
        />
        <button
          onClick={handleSave}
          disabled={saving}
          className="rounded bg-indigo-600 px-3 py-1 text-sm text-white hover:bg-indigo-700 disabled:opacity-50"
        >
          Save
        </button>
      </div>
    </div>
  )
}

function ReportSkeleton() {
  return (
    <div className="space-y-4">
      <div className="rounded-lg border bg-white p-4 shadow-sm">
        <Skeleton className="h-4 w-24" />
        <Skeleton className="mt-2 h-8 w-32" />
        <Skeleton className="mt-2 h-4 w-48" />
        <Skeleton className="mt-2 h-4 w-56" />
      </div>

      <div className="rounded-lg border bg-white p-4 shadow-sm">
        <Skeleton className="h-4 w-32" />
        {Array.from({ length: 2 }).map((_, i) => (
          <div key={i} className="flex justify-between py-1">
            <Skeleton className="h-4 w-28" />
            <Skeleton className="h-4 w-12" />
          </div>
        ))}
      </div>

      <div className="rounded-lg border bg-white p-4 shadow-sm">
        <Skeleton className="h-4 w-32" />
        {Array.from({ length: 2 }).map((_, i) => (
          <div key={i} className="flex justify-between py-1">
            <Skeleton className="h-4 w-28" />
            <Skeleton className="h-4 w-32" />
          </div>
        ))}
      </div>

      <div className="rounded-lg border bg-white p-4 shadow-sm">
        <Skeleton className="h-4 w-32" />
        <Skeleton className="mt-2 h-4 w-20" />
      </div>
    </div>
  )
}

export default function Reports() {
  const now = new Date()
  const [year, setYear] = useState(now.getFullYear())
  const [month, setMonth] = useState(now.getMonth() + 1)
  const { cards } = useCards()
  const [report, setReport] = useState(null)
  const [loading, setLoading] = useState(true)

  const refresh = () => {
    setLoading(true)
    getMonthlyReport(year, month)
      .then(setReport)
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    refresh()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [year, month])

  return (
    <div className="space-y-4">
      <div className="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
        <h1 className="text-lg font-semibold text-gray-900">Reports</h1>
        <div className="flex gap-2">
          <select value={month} onChange={(e) => setMonth(Number(e.target.value))} className="rounded border px-2 py-1 text-sm">
            {MONTH_NAMES.map((name, i) => (
              <option key={name} value={i + 1}>
                {name}
              </option>
            ))}
          </select>
          <input
            type="number"
            value={year}
            onChange={(e) => setYear(Number(e.target.value))}
            className="w-20 rounded border px-2 py-1 text-sm"
          />
        </div>
      </div>

      <div className="rounded-lg border bg-white p-4 shadow-sm">
        <h2 className="mb-2 text-sm font-semibold text-gray-900">Log spend for this month</h2>
        {cards.filter(card=>card.permission !== 'viewer').map((card) => (
          <LogSpendRow key={card.id} card={card} year={year} month={month} onSaved={refresh} />
        ))}
      </div>

      {loading && <ReportSkeleton />}

      {!loading && report && (
        <div className="space-y-4">
          <div className="rounded-lg border bg-white p-4 shadow-sm">
            <p className="text-sm text-gray-600">Total spend</p>
            <p className="text-2xl font-semibold text-gray-900">₹{report.total_spend.toLocaleString('en-IN')}</p>
            <p className="mt-1 text-sm text-gray-600">
              Best card used: {report.best_card_used ? report.best_card_used.card_name : '—'}
            </p>
            <p className="mt-1 text-sm text-gray-600">
              Potential missed rewards this month: ₹{report.potential_missed_rewards_value.toLocaleString('en-IN')}
            </p>
          </div>

          <div className="rounded-lg border bg-white p-4 shadow-sm">
            <h2 className="mb-2 text-sm font-semibold text-gray-900">Waiver progress</h2>
            {report.waiver_progress.map((w) => (
              <div key={w.card_id} className="flex flex-col gap-1 py-2 text-sm sm:flex-row sm:justify-between sm:gap-3">
                <span className="text-gray-700">{w.card_name}</span>
                <span className="text-gray-900">{w.waiver_progress_percentage}%</span>
              </div>
            ))}
          </div>

          <div className="rounded-lg border bg-white p-4 shadow-sm">
            <h2 className="mb-2 text-sm font-semibold text-gray-900">Utilization status</h2>
            {report.utilization_status.map((u) => (
              <div key={u.card_id} className="flex flex-col gap-1 py-2 text-sm sm:flex-row sm:justify-between sm:gap-3">
                <span className="text-gray-700">{u.card_name}</span>
                <span className="text-gray-900">
                  {u.utilization_percentage}% ({u.band.replace(/_/g, ' ')})
                </span>
              </div>
            ))}
          </div>

          <div className="rounded-lg border bg-white p-4 shadow-sm">
            <h2 className="mb-2 text-sm font-semibold text-gray-900">Unused benefits</h2>
            {report.unused_benefits.length === 0 && <p className="text-sm text-gray-500">None.</p>}
            {report.unused_benefits.map((b) => (
              <div key={b.id} className="flex flex-col gap-1 py-2 text-sm sm:flex-row sm:justify-between sm:gap-3">
                <span className="text-gray-700">{b.title}</span>
                <span className="text-gray-900">{b.remaining} remaining</span>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}
