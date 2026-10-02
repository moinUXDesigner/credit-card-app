import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { getCard } from '../api/cards'
import { deleteStatement } from '../api/statements'
import { useStatements } from '../hooks/useStatements'
import Badge from '../components/common/Badge'
import UtilizationBar from '../components/cards/UtilizationBar'
import WaiverProgressBar from '../components/waiver/WaiverProgressBar'
import CardBenefitsPanel from '../components/benefits/CardBenefitsPanel'
import StatementUploadForm from '../components/statements/StatementUploadForm'
import StatementListItem from '../components/statements/StatementListItem'

function Field({ label, value }) {
  return (
    <div>
      <p className="text-xs text-gray-500">{label}</p>
      <p className="text-sm font-medium text-gray-900">{value}</p>
    </div>
  )
}

function DetailsTab({ card }) {
  return (
    <div className="space-y-6">
      <div className="rounded-lg border bg-white p-4 shadow-sm">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-6 xl:grid-cols-3">
          <Field label="Total limit" value={`₹${card.total_limit.toLocaleString('en-IN')}`} />
          <Field label="Current outstanding" value={`₹${card.current_outstanding.toLocaleString('en-IN')}`} />
          <Field label="Shared limit group" value={card.shared_limit_group ?? '—'} />
          <Field label="Statement day" value={card.statement_day} />
          <Field label="Due day" value={card.due_day} />
          <Field label="Annual fee" value={`₹${card.annual_fee_amount.toLocaleString('en-IN')}`} />
          <Field label="Annual fee month" value={card.annual_fee_month} />
          <Field label="Card year start month" value={card.card_year_start_month} />
          <Field label="Reward point balance" value={card.reward_point_balance.toLocaleString('en-IN')} />
          <Field
            label="Reward point value"
            value={card.reward_point_value_estimate ? `₹${card.reward_point_value_estimate}/point` : '—'}
          />
          <Field
            label="Reward rate (general)"
            value={card.reward_rate_general !== null ? `${card.reward_rate_general}%` : '—'}
          />
          <Field
            label="Cashback cap"
            value={card.cashback_cap_amount !== null ? `₹${card.cashback_cap_amount.toLocaleString('en-IN')}` : '—'}
          />
          <Field
            label="Forex markup"
            value={card.forex_markup_percent !== null ? `${card.forex_markup_percent}%` : '—'}
          />
          <Field
            label="Fuel surcharge waiver"
            value={card.fuel_surcharge_waiver_percent !== null ? `${card.fuel_surcharge_waiver_percent}%` : '—'}
          />
          <Field
            label="Insurance cover"
            value={card.insurance_cover_amount !== null ? `₹${card.insurance_cover_amount.toLocaleString('en-IN')}` : '—'}
          />
          <Field label="Lounge access" value={card.lounge_access ? 'Yes' : 'No'} />
          <Field label="Best categories" value={card.best_categories.length ? card.best_categories.join(', ') : '—'} />
        </div>
      </div>

      <div className="rounded-lg border bg-white p-4 shadow-sm">
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
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

      <div className="rounded-lg border bg-white p-4 shadow-sm">
        <CardBenefitsPanel cardId={card.id} readOnly={card.permission === 'viewer'} />
      </div>
    </div>
  )
}

function StatementsTab({ cardId, readOnly }) {
  const { statements, refresh } = useStatements(cardId)

  const handleDelete = async (id) => {
    if (!window.confirm('Delete this statement? Its transactions and any spend they contributed will be removed.')) {
      return
    }
    await deleteStatement(id)
    refresh()
  }

  return (
    <div className="space-y-4">
      {!readOnly && <StatementUploadForm cardId={cardId} onUploaded={refresh} />}

      {statements.length === 0 && <p className="text-sm text-gray-500">No statements uploaded for this card yet.</p>}

      <div className="space-y-3">
        {statements.map((statement) => (
          <StatementListItem key={statement.id} statement={statement} onDelete={handleDelete} readOnly={readOnly} />
        ))}
      </div>
    </div>
  )
}

export default function CardDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [card, setCard] = useState(null)
  const [tab, setTab] = useState('details')

  useEffect(() => {
    const load = () => getCard(id).then(setCard).catch(() => setCard(null))
    load()
    window.addEventListener('sync-data', load)
    return () => window.removeEventListener('sync-data', load)
  }, [id])

  if (!card) {
    return <p className="text-sm text-gray-500">Loading…</p>
  }

  const tabClass = (name) =>
    `border-b-2 px-1 pb-2 text-sm font-medium ${
      tab === name ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'
    }`

  return (
    <div className="space-y-4">
      <div className="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
        <div>
          <button onClick={() => navigate('/cards')} className="text-sm text-indigo-600 hover:underline">
            ← Back to My Cards
          </button>
          <div className="mt-1 flex flex-wrap items-center gap-2">
            <h1 className="text-lg font-semibold text-gray-900">
              {card.bank_name} {card.card_name}
            </h1>
            <Badge color="indigo">{card.network}</Badge>
            <span className="text-sm text-gray-500">•••• {card.last_four_digits}</span>
          </div>
        </div>
        {card.permission !== 'viewer' && <Link to={`/cards/${card.id}/edit`} className="rounded border px-3 py-2 text-sm text-indigo-600 hover:bg-indigo-50">
          Edit card
        </Link>}
      </div>

      <div className="flex gap-6 border-b">
        <button className={tabClass('details')} onClick={() => setTab('details')}>
          Details
        </button>
        <button className={tabClass('statements')} onClick={() => setTab('statements')}>
          Statements
        </button>
      </div>

      {tab === 'details' ? <DetailsTab card={card} /> : <StatementsTab cardId={card.id} readOnly={card.permission === 'viewer'} />}
    </div>
  )
}
