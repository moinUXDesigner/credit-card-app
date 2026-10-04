import MarkAsPaidButton from './MarkAsPaidButton'
import RecentSpends from './RecentSpends'
import { Link } from 'react-router-dom'
import Badge from '../common/Badge'
import UtilizationBar from './UtilizationBar'
import WaiverProgressBar from '../waiver/WaiverProgressBar'

export default function CardListItem({ card, onDelete }) {
  return (
    <div className="relative rounded-lg border bg-white p-4 shadow-sm">
      <div className="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
        <Link to={`/cards/${card.id}`} aria-label={`View ${card.bank_name} ${card.card_name}`} className="block min-w-0 after:absolute after:inset-0 after:rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600">
          <div className="flex flex-wrap items-center gap-2">
            <span className="font-medium text-gray-900">
              {card.bank_name} {card.card_name}
            </span>
            <Badge color="indigo">{card.network}</Badge>
            {card.pending && <Badge color="orange">Pending sync</Badge>}
            {card.permission !== 'owner' && <Badge color="gray">{card.permission}</Badge>}
            <span className="text-sm text-gray-500">•••• {card.last_four_digits}</span>
          </div>
          <p className="mt-1 text-sm text-gray-600">
            Limit ₹{card.total_limit.toLocaleString('en-IN')} · Outstanding ₹
            {card.current_outstanding.toLocaleString('en-IN')}
          </p>
          {card.shared_limit_group && (
            <p className="mt-1 text-xs text-gray-500">
              Shares limit with other cards in group "{card.shared_limit_group}" — utilization below reflects the
              combined balance.
            </p>
          )}
        </Link>
        <div className="relative z-10 flex shrink-0 flex-wrap items-center gap-3 text-sm">
          <MarkAsPaidButton card={card} />
          {card.permission !== 'viewer' && <Link to={`/cards/${card.id}?tab=statements`} className="text-indigo-600 hover:underline">Upload statement</Link>}
          {card.permission !== 'viewer' && <Link to={`/cards/${card.id}/edit`} className="text-indigo-600 hover:underline">
            Edit
          </Link>}
          {card.permission === 'owner' && <button onClick={() => onDelete(card.id)} className="text-red-600 hover:underline">
            Delete
          </button>}
        </div>
      </div>

      <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-6">
        <UtilizationBar
          percentage={card.total_limit > 0 ? card.utilization_percentage : null}
          band={card.utilization_band}
          message={card.utilization_message}
        />
        <WaiverProgressBar
          completed={card.waiver_spend_completed}
          required={card.waiver_spend_required}
          remaining={card.waiver_remaining_spend}
          monthsLeft={card.waiver_months_left}
          suggestedMonthlySpend={card.waiver_suggested_monthly_spend}
        />
      </div>
      <RecentSpends />
    </div>
  )
}
