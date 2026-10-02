import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts'
import { getCategoryColor, getCategoryLabel } from '../../utils/categoryColors'

function formatCurrency(value) {
  return `₹${Math.round(value).toLocaleString('en-IN')}`
}

function ChartTooltip({ active, payload }) {
  if (!active || !payload?.length) return null
  const { category, amount, percentage } = payload[0].payload

  return (
    <div className="rounded border bg-white px-3 py-2 text-sm shadow-md">
      <p className="font-medium text-gray-900">{getCategoryLabel(category)}</p>
      <p className="text-gray-600">
        {formatCurrency(amount)} · {percentage}%
      </p>
    </div>
  )
}

export default function SpendDonutChart({ categories, totalSpend }) {
  return (
    <div className="flex flex-col gap-6 sm:flex-row sm:items-center">
      <div className="relative mx-auto aspect-square w-full max-w-64 shrink-0 sm:w-64">
        <ResponsiveContainer width="100%" height="100%">
          <PieChart>
            <Pie
              data={categories}
              dataKey="amount"
              nameKey="category"
              innerRadius="60%"
              outerRadius="90%"
              paddingAngle={2}
              stroke="none"
              isAnimationActive={false}
            >
              {categories.map((entry) => (
                <Cell key={entry.category} fill={getCategoryColor(entry.category)} />
              ))}
            </Pie>
            <Tooltip content={<ChartTooltip />} />
          </PieChart>
        </ResponsiveContainer>
        <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
          <span className="text-xs text-gray-500">All Categories</span>
          <span className="text-lg font-semibold text-gray-900">{formatCurrency(totalSpend)}</span>
        </div>
      </div>

      <ul className="min-w-0 flex-1 space-y-2">
        {categories.map((entry) => (
          <li key={entry.category} className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-sm">
            <span className="flex min-w-0 items-center gap-2 text-gray-700">
              <span
                className="h-2.5 w-2.5 shrink-0 rounded-full"
                style={{ backgroundColor: getCategoryColor(entry.category) }}
              />
              <span className="break-words">{getCategoryLabel(entry.category)}</span>
            </span>
            <span className="shrink-0 text-gray-900">
              {formatCurrency(entry.amount)} · {entry.percentage}%
            </span>
          </li>
        ))}
      </ul>
    </div>
  )
}
