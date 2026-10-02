import { useEffect, useState } from 'react'
import { getDashboard } from '../api/dashboard'
import UtilizationBar from '../components/cards/UtilizationBar'
import Skeleton from '../components/common/Skeleton'

function DashboardSkeleton() {
  return (
    <div className="space-y-4">
      <Skeleton className="h-6 w-32" />

      <div className="rounded-lg border bg-white p-4 shadow-sm">
        <Skeleton className="h-4 w-40" />
        <Skeleton className="mt-3 h-3 w-full rounded-full" />
      </div>

      <div className="rounded-lg border bg-white p-4 shadow-sm">
        <Skeleton className="h-4 w-56" />
        {Array.from({ length: 3 }).map((_, i) => (
          <div key={i} className="flex flex-col gap-2 border-b py-2 last:border-0 sm:flex-row sm:justify-between">
            <Skeleton className="h-4 w-32" />
            <Skeleton className="h-4 w-24" />
          </div>
        ))}
      </div>

      <div className="rounded-lg border bg-white p-4 shadow-sm">
        <Skeleton className="h-4 w-32" />
        {Array.from({ length: 2 }).map((_, i) => (
          <div key={i} className="flex flex-col gap-2 border-b py-2 last:border-0 sm:flex-row sm:justify-between">
            <Skeleton className="h-4 w-24" />
            <Skeleton className="h-4 w-56" />
          </div>
        ))}
      </div>

      <div className="rounded-lg border bg-white p-4 shadow-sm">
        <Skeleton className="h-4 w-32" />
        {Array.from({ length: 2 }).map((_, i) => (
          <div key={i} className="flex flex-col gap-2 border-b py-2 last:border-0 sm:flex-row sm:justify-between">
            <Skeleton className="h-4 w-24" />
            <Skeleton className="h-4 w-16" />
          </div>
        ))}
      </div>
    </div>
  )
}

export default function Dashboard() {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    getDashboard()
      .then(setData)
      .finally(() => setLoading(false))
  }, [])

  if (loading) return <DashboardSkeleton />
  if (!data) return null

  return (
    <div className="space-y-4">
      <h1 className="text-lg font-semibold text-gray-900">Dashboard</h1>

      <div className="rounded-lg border bg-white p-4 shadow-sm">
        <h2 className="mb-2 text-sm font-semibold text-gray-900">Overall utilization</h2>
        <UtilizationBar percentage={data.overall_utilization.percentage} band={data.overall_utilization.band} />
      </div>

      <div className="rounded-lg border bg-white p-4 shadow-sm">
        <h2 className="mb-2 text-sm font-semibold text-gray-900">Upcoming dates (next 14 days)</h2>
        {data.upcoming_dates.length === 0 && <p className="text-sm text-gray-500">Nothing upcoming.</p>}
        {data.upcoming_dates.map((d, i) => (
          <div key={i} className="flex flex-col gap-1 border-b py-2 text-sm last:border-0 sm:flex-row sm:justify-between sm:gap-4">
            <span className="text-gray-700">
              {d.card_name} — {d.type === 'due' ? 'Payment due' : 'Statement date'}
            </span>
            <span className="text-gray-900">
              {d.date} ({d.days_until === 0 ? 'today' : `${d.days_until}d`})
            </span>
          </div>
        ))}
      </div>

      <div className="rounded-lg border bg-white p-4 shadow-sm">
        <h2 className="mb-2 text-sm font-semibold text-gray-900">Waiver alerts</h2>
        {data.waiver_alerts.length === 0 && <p className="text-sm text-gray-500">No pending waiver targets.</p>}
        {data.waiver_alerts.map((alert) => (
          <div key={alert.card_id} className="flex flex-col gap-1 border-b py-2 text-sm last:border-0 sm:flex-row sm:justify-between sm:gap-4">
            <span className="text-gray-700">{alert.card_name}</span>
            <span className="text-gray-900">
              ₹{alert.remaining_spend.toLocaleString('en-IN')} remaining · {alert.months_left} month
              {alert.months_left === 1 ? '' : 's'} left · ₹{alert.suggested_monthly_spend.toLocaleString('en-IN')}/month
            </span>
          </div>
        ))}
      </div>

      <div className="rounded-lg border bg-white p-4 shadow-sm">
        <h2 className="mb-2 text-sm font-semibold text-gray-900">Unused benefits</h2>
        {data.unused_benefits.length === 0 && <p className="text-sm text-gray-500">None.</p>}
        {data.unused_benefits.map((b) => (
          <div key={b.id} className="flex flex-col gap-1 border-b py-2 text-sm last:border-0 sm:flex-row sm:justify-between sm:gap-4">
            <span className="text-gray-700">{b.title}</span>
            <span className="text-gray-900">{b.remaining} remaining</span>
          </div>
        ))}
      </div>
    </div>
  )
}
