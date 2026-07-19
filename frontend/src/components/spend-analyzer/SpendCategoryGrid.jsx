import { getCategoryColor, getCategoryLabel } from '../../utils/categoryColors'

function formatCurrency(value) {
  return `₹${Math.round(value).toLocaleString('en-IN')}`
}

export default function SpendCategoryGrid({ categories }) {
  return (
    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
      {categories.map((entry) => (
        <div key={entry.category} className="flex flex-col items-center gap-2 rounded-lg border p-4 text-center">
          <span
            className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold text-white"
            style={{ backgroundColor: getCategoryColor(entry.category) }}
          >
            {getCategoryLabel(entry.category).charAt(0)}
          </span>
          <span className="text-sm font-medium text-gray-900">{getCategoryLabel(entry.category)}</span>
          <span className="text-xs text-gray-600">
            {formatCurrency(entry.amount)} · {entry.percentage}%
          </span>
        </div>
      ))}
    </div>
  )
}
