import { Link } from 'react-router-dom'
import { useCards } from '../hooks/useCards'
import { deleteCard } from '../api/cards'
import CardListItem from '../components/cards/CardListItem'

export default function MyCards() {
  const { cards, loading, error, refresh } = useCards()

  const handleDelete = async (id) => {
    if (!window.confirm('Delete this card?')) return
    await deleteCard(id)
    refresh()
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-lg font-semibold text-gray-900">My Cards</h1>
        <Link to="/cards/new" className="rounded bg-indigo-600 px-3 py-2 text-sm text-white hover:bg-indigo-700">
          Add Card
        </Link>
      </div>

      {loading && <p className="text-sm text-gray-500">Loading…</p>}
      {error && <p className="text-sm text-red-600">Failed to load cards.</p>}
      {!loading && cards.length === 0 && (
        <p className="text-sm text-gray-500">No cards yet. Add your first card to get started.</p>
      )}

      <div className="space-y-3">
        {cards.map((card) => (
          <CardListItem key={card.id} card={card} onDelete={handleDelete} />
        ))}
      </div>
    </div>
  )
}
