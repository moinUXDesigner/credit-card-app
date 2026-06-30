import Badge from '../common/Badge'

const EXPIRY_SOON_DAYS = 30

function isExpiringSoon(expiryDate) {
  if (!expiryDate) return false
  const days = (new Date(expiryDate) - new Date()) / (1000 * 60 * 60 * 24)
  return days >= 0 && days <= EXPIRY_SOON_DAYS
}

export default function BenefitListItem({ benefit, onMarkUsed, onDelete }) {
  const exhausted = benefit.remaining <= 0
  const expiringSoon = isExpiringSoon(benefit.expiry_date)

  return (
    <div className="flex items-center justify-between rounded-lg border bg-white p-4 shadow-sm">
      <div>
        <div className="flex items-center gap-2">
          <span className="font-medium text-gray-900">{benefit.title}</span>
          <Badge color="indigo">{benefit.type}</Badge>
          <Badge color="gray">{benefit.frequency}</Badge>
          {expiringSoon && <Badge color="orange">expiring soon</Badge>}
          {exhausted && <Badge color="red">used up</Badge>}
        </div>
        <p className="mt-1 text-sm text-gray-600">
          {benefit.used_count} / {benefit.total_allowed} used
          {benefit.estimated_value != null && ` · est. value ₹${benefit.estimated_value.toLocaleString('en-IN')}`}
          {benefit.expiry_date && ` · expires ${benefit.expiry_date}`}
        </p>
      </div>
      <div className="flex gap-3 text-sm">
        <button
          onClick={() => onMarkUsed(benefit.id)}
          disabled={exhausted}
          className="text-indigo-600 hover:underline disabled:cursor-not-allowed disabled:text-gray-400 disabled:no-underline"
        >
          Mark used
        </button>
        <button onClick={() => onDelete(benefit.id)} className="text-red-600 hover:underline">
          Delete
        </button>
      </div>
    </div>
  )
}
