import { useState } from 'react'
import { useCards } from '../hooks/useCards'
import CardBenefitsPanel from '../components/benefits/CardBenefitsPanel'
import BenefitListItemSkeleton from '../components/benefits/BenefitListItemSkeleton'
import Skeleton from '../components/common/Skeleton'

export default function Benefits() {
  const { cards, loading: cardsLoading } = useCards()
  const [selectedCardId, setSelectedCardId] = useState(null)
  const cardId = selectedCardId ?? cards[0]?.id ?? null

  if (cardsLoading) {
    return (
      <div className="space-y-4">
        <div className="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
          <h1 className="text-lg font-semibold text-gray-900">Benefits</h1>
          <div className="flex flex-wrap items-center gap-3">
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
      <div className="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
        <h1 className="text-lg font-semibold text-gray-900">Benefits</h1>
        <select
          value={cardId ?? ''}
          onChange={(e) => setSelectedCardId(Number(e.target.value))}
          className="min-w-0 max-w-full rounded border px-3 py-2 text-sm"
        >
          {cards.map((c) => (
            <option key={c.id} value={c.id}>
              {c.card_name} ({c.bank_name})
            </option>
          ))}
        </select>
      </div>

      <CardBenefitsPanel cardId={cardId} readOnly={cards.find(c=>String(c.id)===String(cardId))?.permission === 'viewer'} />
    </div>
  )
}
