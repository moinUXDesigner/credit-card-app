import { useEffect, useState } from 'react'
import { getSpendByCategory } from '../api/spendAnalyzer'
import { prepareCategories } from '../utils/categoryColors'
import Skeleton from '../components/common/Skeleton'
import SpendDonutChart from '../components/spend-analyzer/SpendDonutChart'
import SpendCategoryGrid from '../components/spend-analyzer/SpendCategoryGrid'

const PERIOD_OPTIONS = [
  { value: 1, label: 'This Month' },
  { value: 3, label: 'Last 3 Months' },
  { value: 6, label: 'Last 6 Months' },
  { value: 12, label: 'Last 12 Months' },
]

function SpendAnalyzerSkeleton() {
  return (
    <div className="rounded-lg border bg-white p-4 shadow-sm">
      <div className="flex flex-col gap-6 sm:flex-row sm:items-center">
        <Skeleton className="mx-auto aspect-square w-64 shrink-0 rounded-full" />
        <div className="min-w-0 flex-1 space-y-3">
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} className="h-4 w-full" />
          ))}
        </div>
      </div>
    </div>
  )
}

export default function SpendAnalyzer() {
  const [months, setMonths] = useState(6)
  const [view, setView] = useState('chart')
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    setLoading(true)
    getSpendByCategory(months)
      .then(setData)
      .finally(() => setLoading(false))
  }, [months])

  const categories = data ? prepareCategories(data.categories) : []

  return (
    <div className="space-y-4">
      <div className="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
        <h1 className="text-lg font-semibold text-gray-900">Spend Analyzer</h1>
        <select
          value={months}
          onChange={(e) => setMonths(Number(e.target.value))}
          className="rounded border px-3 py-2 text-sm"
        >
          {PERIOD_OPTIONS.map((opt) => (
            <option key={opt.value} value={opt.value}>
              {opt.label}
            </option>
          ))}
        </select>
      </div>

      {loading && <SpendAnalyzerSkeleton />}

      {!loading && data && (
        <div className="rounded-lg border bg-white p-4 shadow-sm">
          <div className="mb-4 flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
            <p className="text-sm text-gray-600">
              Analyzing spend for period:{' '}
              <span className="font-medium text-gray-900">
                {data.from.month}/{data.from.year} - {data.to.month}/{data.to.year}
              </span>
            </p>
            <div className="flex overflow-hidden rounded border">
              <button
                type="button"
                onClick={() => setView('chart')}
                className={`px-3 py-1.5 text-sm ${
                  view === 'chart' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'
                }`}
              >
                Chart
              </button>
              <button
                type="button"
                onClick={() => setView('grid')}
                className={`border-l px-3 py-1.5 text-sm ${
                  view === 'grid' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'
                }`}
              >
                Grid
              </button>
            </div>
          </div>

          {categories.length === 0 ? (
            <p className="py-8 text-center text-sm text-gray-500">No spend logged for this period yet.</p>
          ) : view === 'chart' ? (
            <SpendDonutChart categories={categories} totalSpend={data.total_spend} />
          ) : (
            <SpendCategoryGrid categories={categories} />
          )}
        </div>
      )}
    </div>
  )
}
