import { useEffect, useState } from 'react'
import { getRecommendation } from '../api/recommendations'
import { CATEGORIES } from '../schemas/cardSchema'
import ScoreCard from '../components/recommendation/ScoreCard'
import ScoreCardSkeleton from '../components/recommendation/ScoreCardSkeleton'

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
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-lg font-semibold text-gray-900">Recommendation</h1>
        <select value={category} onChange={(e) => setCategory(e.target.value)} className="rounded border px-3 py-2 text-sm">
          <option value="">General spend</option>
          {CATEGORIES.map((c) => (
            <option key={c} value={c}>
              {c}
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
  )
}
