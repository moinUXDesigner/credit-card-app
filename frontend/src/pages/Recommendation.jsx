import { useEffect, useState } from 'react'
import { getMonthlyPlan, getRecommendation } from '../api/recommendations'
import { CATEGORIES } from '../schemas/cardSchema'
import ScoreCard from '../components/recommendation/ScoreCard'
import ScoreCardSkeleton from '../components/recommendation/ScoreCardSkeleton'

function MonthlyPlan() {
  const [categories, setCategories] = useState([])
  const [topN, setTopN] = useState(2)
  const [results, setResults] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    setLoading(true)
    getMonthlyPlan(categories, topN)
      .then(setResults)
      .finally(() => setLoading(false))
  }, [categories, topN])

  const toggleCategory = (c) => {
    setCategories((prev) => (prev.includes(c) ? prev.filter((x) => x !== c) : [...prev, c]))
  }

  return (
    <div className="space-y-3 rounded-lg border bg-white p-4 shadow-sm">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-sm font-semibold text-gray-900">Monthly plan</h2>
          <p className="text-xs text-gray-500">
            Pick the categories you plan to spend on this month — the top cards blend fee-waiver urgency with how
            well each card rewards those categories.
          </p>
        </div>
        <label className="flex shrink-0 items-center gap-2 text-xs text-gray-600">
          Show top
          <select
            value={topN}
            onChange={(e) => setTopN(Number(e.target.value))}
            className="rounded border px-2 py-1 text-sm"
          >
            {[1, 2, 3, 4].map((n) => (
              <option key={n} value={n}>
                {n}
              </option>
            ))}
          </select>
        </label>
      </div>

      <div className="flex flex-wrap gap-2">
        {CATEGORIES.filter((c) => c !== 'other').map((c) => (
          <button
            key={c}
            type="button"
            onClick={() => toggleCategory(c)}
            className={`rounded-full border px-3 py-1 text-xs ${
              categories.includes(c)
                ? 'border-indigo-600 bg-indigo-600 text-white'
                : 'border-gray-300 bg-white text-gray-700'
            }`}
          >
            {c.replace(/_/g, ' ')}
          </button>
        ))}
      </div>

      {loading ? (
        <div className="space-y-3">
          {Array.from({ length: topN }).map((_, i) => (
            <ScoreCardSkeleton key={i} />
          ))}
        </div>
      ) : (
        <div className="space-y-3">
          {results.length === 0 && (
            <p className="text-sm text-gray-500">Add an active card to get a monthly plan.</p>
          )}
          {results.map((result, i) => (
            <ScoreCard key={result.card_id} rank={i + 1} result={result} />
          ))}
        </div>
      )}
    </div>
  )
}

export default function Recommendation() {
  const [category, setCategory] = useState('')
  const [results, setResults] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    setLoading(true)
    getRecommendation(category || undefined)
      .then(setResults)
      .finally(() => setLoading(false))
  }, [category])

  return (
    <div className="space-y-6">
      <h1 className="text-lg font-semibold text-gray-900">Recommendation</h1>

      <MonthlyPlan />

      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <h2 className="text-sm font-semibold text-gray-900">Best card by category</h2>
          <select value={category} onChange={(e) => setCategory(e.target.value)} className="rounded border px-3 py-2 text-sm">
            <option value="">General spend</option>
            {CATEGORIES.map((c) => (
              <option key={c} value={c}>
                {c.replace(/_/g, ' ')}
              </option>
            ))}
          </select>
        </div>

        {!loading && results.length === 0 && (
          <p className="text-sm text-gray-500">Add an active card to get a recommendation.</p>
        )}

        {loading ? (
          <div className="space-y-3">
            {Array.from({ length: 3 }).map((_, i) => (
              <ScoreCardSkeleton key={i} />
            ))}
          </div>
        ) : (
          <div className="space-y-3">
            {results.map((result, i) => (
              <ScoreCard key={result.card_id} rank={i + 1} result={result} />
            ))}
          </div>
        )}
      </div>
    </div>
  )
}
