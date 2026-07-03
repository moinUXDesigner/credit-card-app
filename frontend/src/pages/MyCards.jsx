import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useCards } from '../hooks/useCards'
import { deleteCard } from '../api/cards'
import CardListItem from '../components/cards/CardListItem'
import CardListItemSkeleton from '../components/cards/CardListItemSkeleton'

const SORT_FIELDS = [
  { key: 'bank_name', label: 'Bank Name', type: 'string' },
  { key: 'card_name', label: 'Card Name', type: 'string' },
  { key: 'total_limit', label: 'Credit Limit', type: 'number' },
  { key: 'utilization_percentage', label: 'Usage', type: 'number' },
  { key: 'current_outstanding', label: 'Outstanding Balance', type: 'number' },
  { key: 'annual_fee_amount', label: 'Annual Fee', type: 'number' },
  { key: 'waiver_progress_percentage', label: 'Waiver Progress %', type: 'number' },
  { key: 'reward_point_balance', label: 'Reward Point Balance', type: 'number' },
  { key: 'due_day', label: 'Due Date', type: 'number' },
]
const DEFAULT_SORT_KEY = 'bank_name'
const DEFAULT_SORT_DIR = 'asc'
const SORT_STORAGE_KEY = 'ccapp-mycards-sort'

export default function MyCards() {
  const { cards, loading, error, refresh } = useCards()

  const [sort, setSort] = useState(() => {
    try {
      const raw = localStorage.getItem(SORT_STORAGE_KEY)
      if (raw) {
        const parsed = JSON.parse(raw)
        if (SORT_FIELDS.some((f) => f.key === parsed.key) && (parsed.dir === 'asc' || parsed.dir === 'desc')) {
          return parsed
        }
      }
    } catch {
      // ignore malformed/unavailable storage, fall through to default
    }
    return { key: DEFAULT_SORT_KEY, dir: DEFAULT_SORT_DIR }
  })

  useEffect(() => {
    try {
      localStorage.setItem(SORT_STORAGE_KEY, JSON.stringify(sort))
    } catch {
      // storage unavailable/quota exceeded — non-fatal, sort still works this session
    }
  }, [sort])

  const sortedCards = useMemo(() => {
    const field = SORT_FIELDS.find((f) => f.key === sort.key) ?? SORT_FIELDS[0]
    const dir = sort.dir === 'desc' ? -1 : 1

    return [...cards].sort((a, b) => {
      const aVal = a[field.key]
      const bVal = b[field.key]

      if (field.type === 'string') {
        return dir * String(aVal).localeCompare(String(bVal), undefined, { sensitivity: 'base' })
      }
      return dir * (Number(aVal) - Number(bVal))
    })
  }, [cards, sort])

  const handleDelete = async (id) => {
    if (!window.confirm('Delete this card?')) return
    await deleteCard(id)
    refresh()
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-lg font-semibold text-gray-900">My Cards</h1>
        <div className="flex gap-2">
          <Link
            to="/cards/import"
            className="rounded border border-indigo-600 px-3 py-2 text-sm text-indigo-600 hover:bg-indigo-50"
          >
            Bulk Upload
          </Link>
          <Link to="/cards/new" className="rounded bg-indigo-600 px-3 py-2 text-sm text-white hover:bg-indigo-700">
            Add Card
          </Link>
        </div>
      </div>

      <div className="flex items-center justify-end gap-2">
        <label htmlFor="card-sort-field" className="text-sm text-gray-600">
          Sort by
        </label>
        <select
          id="card-sort-field"
          value={sort.key}
          onChange={(e) => setSort((s) => ({ ...s, key: e.target.value }))}
          className="rounded border px-2 py-1 text-sm"
        >
          {SORT_FIELDS.map((f) => (
            <option key={f.key} value={f.key}>
              {f.label}
            </option>
          ))}
        </select>
        <button
          type="button"
          onClick={() => setSort((s) => ({ ...s, dir: s.dir === 'asc' ? 'desc' : 'asc' }))}
          className="rounded border px-2 py-1 text-sm text-gray-700 hover:bg-gray-50"
          aria-label={sort.dir === 'asc' ? 'Sort ascending' : 'Sort descending'}
          title={sort.dir === 'asc' ? 'Ascending' : 'Descending'}
        >
          {sort.dir === 'asc' ? '↑' : '↓'}
        </button>
      </div>

      {error && <p className="text-sm text-red-600">Failed to load cards.</p>}
      {!loading && sortedCards.length === 0 && (
        <p className="text-sm text-gray-500">No cards yet. Add your first card to get started.</p>
      )}

      {loading ? (
        <div className="space-y-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <CardListItemSkeleton key={i} />
          ))}
        </div>
      ) : (
        <div className="space-y-3">
          {sortedCards.map((card) => (
            <CardListItem key={card.id} card={card} onDelete={handleDelete} />
          ))}
        </div>
      )}
    </div>
  )
}
