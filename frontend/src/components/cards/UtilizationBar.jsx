const BAND_COLORS = {
  good: { bar: 'bg-green-500', text: 'text-green-700' },
  caution: { bar: 'bg-yellow-500', text: 'text-yellow-700' },
  avoid_further_use: { bar: 'bg-orange-500', text: 'text-orange-700' },
  urgent_repayment: { bar: 'bg-red-500', text: 'text-red-700' },
}

export default function UtilizationBar({ percentage, band, message }) {
  const colors = BAND_COLORS[band] ?? BAND_COLORS.good
  const width = Math.min(100, Math.max(0, percentage))

  return (
    <div>
      <div className="flex flex-wrap items-center justify-between gap-2 text-xs">
        <span className="text-gray-600">Utilization</span>
        <span className={`font-medium ${colors.text}`}>{percentage.toFixed(1)}%</span>
      </div>
      <div className="mt-1 h-2 w-full rounded-full bg-gray-200">
        <div className={`h-2 rounded-full ${colors.bar}`} style={{ width: `${width}%` }} />
      </div>
      {message && <p className={`mt-1 text-xs ${colors.text}`}>{message}</p>}
    </div>
  )
}
