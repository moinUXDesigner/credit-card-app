import { useState } from 'react'
import { useCards } from '../hooks/useCards'
import { useBenefits } from '../hooks/useBenefits'
import { createBenefit, deleteBenefit, markBenefitUsed } from '../api/benefits'
import BenefitForm from '../components/benefits/BenefitForm'
import BenefitListItem from '../components/benefits/BenefitListItem'
import BenefitListItemSkeleton from '../components/benefits/BenefitListItemSkeleton'
import Skeleton from '../components/common/Skeleton'

export default function Benefits() {
  const { cards, loading: cardsLoading } = useCards()
  const [selectedCardId, setSelectedCardId] = useState(null)
  const cardId = selectedCardId ?? cards[0]?.id ?? null
  const { benefits, refresh } = useBenefits(cardId)
  const [showForm, setShowForm] = useState(false)

  const handleAdd = async (values) => {
    await createBenefit(cardId, values)
    setShowForm(false)
    refresh()
  }

  const handleMarkUsed = async (id) => {
    await markBenefitUsed(id)
    refresh()
  }

  const handleDelete = async (id) => {
    if (!window.confirm('Delete this benefit?')) return
    await deleteBenefit(id)
    refresh()
  }

  if (cardsLoading) {
    return (
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <h1 className="text-lg font-semibold text-gray-900">Benefits</h1>
          <div className="flex items-center gap-3">
            <Skeleton className="h-9 w-40" />
            <Skeleton className="h-9 w-28" />
          </div>
        </div>
        <div className="space-y-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <BenefitListItemSkeleton key={i} />
          ))}
        </div>
      </div>
    )
  }
  if (cards.length === 0) {
    return <p className="text-sm text-gray-500">Add a card first to start tracking its benefits.</p>
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-lg font-semibold text-gray-900">Benefits</h1>
        <div className="flex items-center gap-3">
          <select
            value={cardId ?? ''}
            onChange={(e) => setSelectedCardId(Number(e.target.value))}
            className="rounded border px-3 py-2 text-sm"
          >
            {cards.map((c) => (
              <option key={c.id} value={c.id}>
                {c.card_name} ({c.bank_name})
              </option>
            ))}
          </select>
          <button
            onClick={() => setShowForm((v) => !v)}
            className="rounded bg-indigo-600 px-3 py-2 text-sm text-white hover:bg-indigo-700"
          >
            {showForm ? 'Close' : 'Add Benefit'}
          </button>
        </div>
      </div>

      {showForm && <BenefitForm onSubmit={handleAdd} onCancel={() => setShowForm(false)} />}

      {benefits.length === 0 && <p className="text-sm text-gray-500">No benefits tracked for this card yet.</p>}

      <div className="space-y-3">
        {benefits.map((benefit) => (
          <BenefitListItem key={benefit.id} benefit={benefit} onMarkUsed={handleMarkUsed} onDelete={handleDelete} />
        ))}
      </div>
    </div>
  )
}
