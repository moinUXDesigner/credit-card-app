import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import CardForm from '../components/cards/CardForm'
import CardAnalysisUpload from '../components/cards/CardAnalysisUpload'
import { createCard, getCard, updateCard } from '../api/cards'
import { createBenefit } from '../api/benefits'
import Skeleton from '../components/common/Skeleton'

function toFormValues(card) {
  const values = {}
  for (const [key, value] of Object.entries(card)) {
    if (value === null || value === undefined) continue
    values[key] = value
  }
  const numericFields = [
    'total_limit', 'current_outstanding', 'statement_day', 'due_day', 'annual_fee_amount',
    'annual_fee_month', 'waiver_spend_required', 'waiver_spend_completed', 'card_year_start_month',
    'reward_point_balance', 'reward_point_value_estimate', 'reward_rate_general', 'cashback_cap_amount',
    'forex_markup_percent', 'fuel_surcharge_waiver_percent', 'insurance_cover_amount',
  ]
  for (const field of numericFields) {
    if (values[field] !== undefined) values[field] = String(values[field])
  }
  return values
}

function CardFormSkeleton() {
  return (
    <div className="space-y-6 rounded-lg border bg-white p-4 shadow-sm sm:p-6">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        {Array.from({ length: 16 }).map((_, i) => (
          <div key={i}>
            <Skeleton className="h-4 w-28" />
            <Skeleton className="mt-1 h-9 w-full" />
          </div>
        ))}
      </div>
      <Skeleton className="h-9 w-32" />
    </div>
  )
}

export default function CardFormPage() {
  const { id } = useParams()
  const isEdit = Boolean(id)
  const [initialValues, setInitialValues] = useState(isEdit ? null : {})
  const [formKey, setFormKey] = useState(0)
  const [suggestedBenefits, setSuggestedBenefits] = useState([])
  const [error, setError] = useState(null)
  const navigate = useNavigate()

  const handleAnalyzed = (result) => {
    setInitialValues((prev) => ({ ...prev, ...toFormValues(result.card) }))
    setSuggestedBenefits(result.suggested_benefits.map((b) => ({ ...b, selected: true })))
    setFormKey((k) => k + 1)
  }

  const toggleBenefit = (index) => {
    setSuggestedBenefits((prev) => prev.map((b, i) => (i === index ? { ...b, selected: !b.selected } : b)))
  }

  useEffect(() => {
    if (!isEdit) return
    getCard(id).then((card) => {
      setInitialValues({
        ...card,
        total_limit: String(card.total_limit),
        current_outstanding: String(card.current_outstanding),
        annual_fee_amount: String(card.annual_fee_amount),
        waiver_spend_required: String(card.waiver_spend_required),
        waiver_spend_completed: String(card.waiver_spend_completed),
        reward_point_balance: String(card.reward_point_balance),
        reward_point_value_estimate: String(card.reward_point_value_estimate),
        reward_rate_general: card.reward_rate_general != null ? String(card.reward_rate_general) : '',
        cashback_cap_amount: card.cashback_cap_amount != null ? String(card.cashback_cap_amount) : '',
        forex_markup_percent: card.forex_markup_percent != null ? String(card.forex_markup_percent) : '',
        fuel_surcharge_waiver_percent:
          card.fuel_surcharge_waiver_percent != null ? String(card.fuel_surcharge_waiver_percent) : '',
        insurance_cover_amount: card.insurance_cover_amount != null ? String(card.insurance_cover_amount) : '',
      })
    })
  }, [id, isEdit])

  const handleSubmit = async (values) => {
    setError(null)
    try {
      if (isEdit) {
        await updateCard(id, values)
      } else {
        const card = await createCard(values)
        const accepted = suggestedBenefits.filter((b) => b.selected)
        await Promise.all(
          accepted.map(({ type, title, frequency, total_allowed, expiry_date, estimated_value }) =>
            createBenefit(card.id, { type, title, frequency, total_allowed, expiry_date, estimated_value }),
          ),
        )
      }
      navigate('/cards')
    } catch (err) {
      setError(err.response?.data?.message ?? 'Failed to save card.')
    }
  }

  if (isEdit && initialValues?.permission === 'viewer') return <p>This shared card is read-only.</p>

  if (isEdit && !initialValues) {
    return (
      <div className="space-y-4">
        <h1 className="text-lg font-semibold text-gray-900">Edit Card</h1>
        <CardFormSkeleton />
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <h1 className="text-lg font-semibold text-gray-900">{isEdit ? 'Edit Card' : 'Add Card'}</h1>
      {error && <p className="rounded bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}

      {!isEdit && <CardAnalysisUpload onAnalyzed={handleAnalyzed} />}

      <CardForm
        key={formKey}
        initialValues={initialValues}
        onSubmit={handleSubmit}
        submitLabel={isEdit ? 'Save changes' : 'Add card'}
      />

      {!isEdit && suggestedBenefits.length > 0 && (
        <div className="space-y-2 rounded-lg border bg-white p-4 shadow-sm">
          <h2 className="text-sm font-semibold text-gray-900">Suggested benefits</h2>
          <p className="text-xs text-gray-500">
            Found from the card issuer's website. Uncheck anything you don't want added — the rest will be created
            as benefits on this card once you save it.
          </p>
          {suggestedBenefits.map((benefit, i) => (
            <label key={i} className="flex items-start gap-2 border-t py-2 text-sm first:border-t-0">
              <input
                type="checkbox"
                checked={benefit.selected}
                onChange={() => toggleBenefit(i)}
                className="mt-0.5"
              />
              <span>
                <span className="font-medium text-gray-900">{benefit.title}</span>
                <span className="ml-2 text-xs text-gray-500">
                  {benefit.frequency.replace(/_/g, ' ')} · up to {benefit.total_allowed}
                  {benefit.estimated_value ? ` · ~₹${benefit.estimated_value.toLocaleString('en-IN')}` : ''}
                </span>
              </span>
            </label>
          ))}
        </div>
      )}
    </div>
  )
}
