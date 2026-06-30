import { useCards } from '../hooks/useCards'

const MONTH_NAMES = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
]

export default function CalendarPage() {
  const { cards, loading } = useCards()

  if (loading) return <p className="text-sm text-gray-500">Loading…</p>

  return (
    <div className="space-y-4">
      <h1 className="text-lg font-semibold text-gray-900">Calendar</h1>

      {cards.length === 0 && <p className="text-sm text-gray-500">Add a card to see its key dates here.</p>}

      <div className="space-y-3">
        {cards.map((card) => (
          <div key={card.id} className="rounded-lg border bg-white p-4 shadow-sm">
            <h2 className="font-medium text-gray-900">
              {card.card_name} <span className="text-sm text-gray-500">({card.bank_name})</span>
            </h2>
            <dl className="mt-2 grid grid-cols-3 gap-4 text-sm">
              <div>
                <dt className="text-gray-500">Statement date</dt>
                <dd className="text-gray-900">Day {card.statement_day} of every month</dd>
              </div>
              <div>
                <dt className="text-gray-500">Due date</dt>
                <dd className="text-gray-900">Day {card.due_day} of every month</dd>
              </div>
              <div>
                <dt className="text-gray-500">Annual fee month</dt>
                <dd className="text-gray-900">{MONTH_NAMES[card.annual_fee_month - 1]}</dd>
              </div>
            </dl>
          </div>
        ))}
      </div>
    </div>
  )
}
