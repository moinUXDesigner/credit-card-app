import { Link } from 'react-router-dom'
import Badge from '../common/Badge'
import UtilizationBar from './UtilizationBar'
import WaiverProgressBar from '../waiver/WaiverProgressBar'

export default function CardListItem({ card, onDelete }) {
  return (
    <div className="rounded-lg border bg-white p-4 shadow-sm">
      <div className="flex items-center justify-between">
        <div>
          <div className="flex items-center gap-2">
            <span className="font-medium text-gray-900">
              {card.bank_name} {card.card_name}
            </span>
            <Badge color="indigo">{card.network}</Badge>
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
        </div>
        <div className="flex gap-3 text-sm">
          <Link to={`/cards/${card.id}/edit`} className="text-indigo-600 hover:underline">
            Edit
          </Link>
          <button onClick={() => onDelete(card.id)} className="text-red-600 hover:underline">
            Delete
          </button>
        </div>
      </div>

      <div className="mt-4 grid grid-cols-2 gap-6">
        <UtilizationBar
          percentage={card.utilization_percentage}
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
    </div>
  )
}
