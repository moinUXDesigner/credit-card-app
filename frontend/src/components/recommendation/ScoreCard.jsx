const LABELS = {
  waiver_urgency_score: 'Waiver urgency',
  reward_category_score: 'Reward category match',
  unused_benefit_score: 'Unused benefits',
  utilization_penalty: 'Utilization penalty',
  due_date_risk_penalty: 'Due date risk penalty',
}

export default function ScoreCard({ rank, result }) {
  const { card_name, total_score, breakdown } = result

  return (
    <div className="rounded-lg border bg-white p-4 shadow-sm">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex min-w-0 flex-1 items-center gap-3">
          <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700">
            {rank}
          </span>
          <span className="font-medium text-gray-900">{card_name}</span>
        </div>
        <span className="text-lg font-semibold text-gray-900">{total_score.toFixed(1)} pts</span>
      </div>
      <div className="mt-3 grid grid-cols-2 gap-x-6 gap-y-1 text-xs text-gray-600 md:grid-cols-5">
        {Object.entries(breakdown).map(([key, value]) => (
          <div key={key}>
            <div>{LABELS[key] ?? key}</div>
            <div className={`font-medium ${key.includes('penalty') ? 'text-red-600' : 'text-green-700'}`}>
              {key.includes('penalty') ? '-' : '+'}
              {value}
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
