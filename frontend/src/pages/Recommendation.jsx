import client from '../api/client'
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
      <div className="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
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
  const [explanation,setExplanation]=useState(null), [explaining,setExplaining]=useState(false), [explainError,setExplainError]=useState('')
  const [category, setCategory] = useState('')
  const [results, setResults] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    setLoading(true)
    setExplanation(null)
    getRecommendation(category || undefined)
      .then(setResults)
      .finally(() => setLoading(false))
  }, [category])

  return (
    <div className="space-y-6">
      <h1 className="text-lg font-semibold text-gray-900">Recommendation</h1>

      <MonthlyPlan />
      <button disabled={explaining || !results.length} className="rounded bg-indigo-600 px-4 py-2 text-white disabled:opacity-50" onClick={async()=>{setExplaining(true);setExplainError('');try{setExplanation((await client.post('/recommendation/explain',{category:category||null})).data)}catch(e){setExplainError(e.response?.data?.message??'Explanation unavailable.')}finally{setExplaining(false)}}}>{explaining?'Explaining…':'Explain recommendation'}</button>
      {explainError && <p role="alert" className="text-red-700">{explainError}</p>}
      {explanation && <div className="space-y-3 rounded border bg-white p-4"><h2 className="font-medium">{explanation.source==='ai'?'AI-generated explanation':'Score-based explanation'}</h2>{explanation.explanations.map(row=><div key={row.card_id}><h3 className="font-medium">{results.find(c=>c.card_id===row.card_id)?.card_name}</h3><p className="text-sm">{row.reason}</p><p className="text-sm text-gray-600">{row.tradeoff}</p></div>)}</div>}

      <div className="space-y-4">
        <div className="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
          <h2 className="text-sm font-semibold text-gray-900">Best card by category</h2>
          <select value={category} onChange={(e) => setCategory(e.target.value)} className="min-w-0 max-w-full rounded border px-3 py-2 text-sm">
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
