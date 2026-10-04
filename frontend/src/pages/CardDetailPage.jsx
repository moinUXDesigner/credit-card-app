import MarkAsPaidButton from '../components/cards/MarkAsPaidButton'
import RecentSpends from '../components/cards/RecentSpends'
import { useCallback, useEffect, useState, useRef } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { getCard } from '../api/cards'
import { previewStatement, deleteStatement } from '../api/statements'
import { useStatements } from '../hooks/useStatements'
import { refreshData } from '../sync/engine'
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
      <div className="rounded-lg border bg-white p-4 shadow-sm"><RecentSpends /></div>
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
      </div>

      <div className="rounded-lg border bg-white p-4 shadow-sm">
        <CardBenefitsPanel cardId={card.id} readOnly={card.permission === 'viewer'} />
      </div>
    </div>
  )
}

function StatementsTab({ card }) {
  const readOnly = card.permission === 'viewer'
  const { statements, loading, error: listError, refresh } = useStatements(card.id)
  const [review, setReview] = useState(null)
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(null)
  const changed = async () => { await refreshData() }
  const handleDelete = async (id) => {
    if (!window.confirm('Delete this statement? Its imported transactions will be removed and spend recalculated.')) return
    setError(null)
    try { await deleteStatement(id); await changed() }
    catch (err) { setError(err.response?.data?.message ?? err.message) }
  }
  const handleReview = async (statement) => {
    setError(null); setBusy(statement.id)
    try { setReview(await previewStatement(null, null, statement.id)) }
    catch (err) { setError(err.response?.data?.message ?? err.message) }
    finally { setBusy(null) }
  }
  return <div className="space-y-4">
    {!readOnly && <StatementUploadForm key={review?.preview_id ?? 'upload'} card={card} initialPreview={review} onReviewClosed={() => setReview(null)} onUploaded={changed} />}
    {(error || listError) && <p role="alert" className="text-sm text-red-700">{error || listError}<button className="ml-2 underline" onClick={refresh}>Retry</button></p>}
    {loading && <p className="text-sm text-gray-500">Loading statements…</p>}
    {!loading && !listError && statements.length === 0 && <p className="text-sm text-gray-500">No statements uploaded for this card yet.</p>}
    <div className="space-y-3">{statements.map((statement) => <StatementListItem key={statement.id} statement={statement} onDelete={handleDelete} onReview={handleReview} onCategoryChanged={changed} reviewing={busy === statement.id} readOnly={readOnly} />)}</div>
  </div>
}

export default function CardDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [card, setCard] = useState(null)
  const [search, setSearch] = useSearchParams()
  const tab = search.get('tab') === 'statements' ? 'statements' : 'details'
  const setTab = (name) => setSearch(name === 'statements' ? { tab: name } : {})
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const requestId = useRef(0)
  const invalidatePending = useCallback(() => { requestId.current++ }, [])
  const load = useCallback(async () => {
    const current = ++requestId.current
    setLoading(true); setError(null)
    try {
      const data = await getCard(id)
      if (current === requestId.current) setCard(data)
    } catch (err) {
      if (current !== requestId.current) return
      if ([403, 404].includes(err.response?.status)) setCard(null)
      setError([403, 404].includes(err.response?.status) ? 'This card is unavailable or you no longer have access.' : 'Could not refresh this card. Please retry.')
    } finally { if (current === requestId.current) setLoading(false) }
  }, [id])

  useEffect(() => {
    setCard(null)
    load()
    window.addEventListener('sync-data', load)
    return () => { invalidatePending(); window.removeEventListener('sync-data', load) }
  }, [load, invalidatePending])

  if (loading && !card) return <p className="text-sm text-gray-500">Loading…</p>
  if (error && !card) return <div role="alert"><p>{error}</p><button onClick={load} className="mt-2 text-indigo-600">Retry</button><Link to="/cards" className="ml-4 text-indigo-600">Back to My Cards</Link></div>
  if (!card) return null

  const tabClass = (name) =>
    `border-b-2 px-1 pb-2 text-sm font-medium ${
      tab === name ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'
    }`

  return (
    <div className="space-y-4">
      {error && <p role="alert" className="text-sm text-red-700">{error}<button onClick={load} className="ml-2 underline">Retry</button></p>}
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
        {card.permission !== 'viewer' && <div className="flex flex-wrap items-center gap-3"><MarkAsPaidButton card={card} /><Link to={`/cards/${card.id}?tab=statements`} className="rounded border px-3 py-2 text-sm text-indigo-600">Upload statement</Link><Link to={`/cards/${card.id}/edit`} className="rounded border px-3 py-2 text-sm text-indigo-600 hover:bg-indigo-50">
          Edit card
        </Link></div>}
      </div>

      <div className="flex gap-6 border-b">
        <button className={tabClass('details')} onClick={() => setTab('details')}>
          Details
        </button>
        <button className={tabClass('statements')} onClick={() => setTab('statements')}>
          Statements
        </button>
      </div>

      {tab === 'details' ? <DetailsTab card={card} /> : <StatementsTab card={card} />}
    </div>
  )
}
