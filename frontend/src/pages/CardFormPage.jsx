import { useEffect, useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import CardForm from '../components/cards/CardForm'
import CardAnalysisUpload from '../components/cards/CardAnalysisUpload'
import { createCard, getCard, updateCard, listCards } from '../api/cards'
import { createBenefit } from '../api/benefits'
import { previewStatement, confirmStatement, uploadStatement } from '../api/statements'
import StatementReview from '../components/statements/StatementReview'
import { refreshData, resolveCardId } from '../sync/engine'
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
  const [existingCards, setExistingCards] = useState([])
  const [preview, setPreview] = useState(null)
  const [reviewed, setReviewed] = useState(null)
  const [savedCard, setSavedCard] = useState(null)
  const completedBenefits = useRef(new Set())
  const statementAttached = useRef(false)
  const attachmentPayload = useRef(null)
  const lastValues = useRef(null)
  const statementFile = useRef(null)
  const [retrying, setRetrying] = useState(false)
  const [suggestedBenefits, setSuggestedBenefits] = useState([])
  const [error, setError] = useState(null)
  const navigate = useNavigate()

  const handleAnalyzed = async (result, file) => {
    setInitialValues((prev) => ({ ...prev, ...toFormValues(result.card) }))
    setSuggestedBenefits((result.suggested_benefits ?? []).map((b) => ({ ...b, selected: true })))
    setReviewed(null)
    setPreview(null)
    statementFile.current = file.type === 'application/pdf' ? file : null
    if (statementFile.current) setPreview(result.preview ?? await previewStatement(file))
  }

  const toggleBenefit = (index) => {
    setSuggestedBenefits((prev) => prev.map((b, i) => (i === index ? { ...b, selected: !b.selected } : b)))
  }

  useEffect(() => { listCards().then(setExistingCards).catch(() => {}) }, [])

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
    }).catch((err) => setError(err.response?.data?.message ?? 'Could not load this card.'))
  }, [id, isEdit])

  const handleSubmit = async (values) => {
    if (retrying) return
    setRetrying(true)
    setError(null)
    lastValues.current = values
    try {
      if (isEdit) {
        await updateCard(id, values)
        await refreshData()
        navigate(`/cards/${id}`)
        return
      }
      const card = savedCard ?? await createCard(values)
      setSavedCard(card)
      const failures = []
      for (const [index, benefit] of suggestedBenefits.entries()) {
        if (!benefit.selected || completedBenefits.current.has(index)) continue
        try {
          const { type, title, frequency, total_allowed, expiry_date, estimated_value } = benefit
          await createBenefit(card.id, { type, title, frequency, total_allowed, expiry_date, estimated_value })
          completedBenefits.current.add(index)
        } catch (err) { failures.push(err.response?.data?.message ?? 'Could not save a selected benefit.') }
      }
      if (statementFile.current && !statementAttached.current) {
        try {
          // Synchronization may replace a temporary card ID before confirmation.
          await refreshData()
          const cardId = await resolveCardId(card.id)
          if (reviewed) {
            if (!attachmentPayload.current) {
              const fresh = await getCard(cardId)
              attachmentPayload.current = { ...reviewed, revision: fresh.revision }
            }
            await confirmStatement(cardId, attachmentPayload.current, statementFile.current)
          } else {
            const date = preview?.summary?.statement_date
            await uploadStatement(cardId, statementFile.current,
              date ? Number(date.slice(5, 7)) : new Date().getMonth() + 1,
              date ? Number(date.slice(0, 4)) : new Date().getFullYear())
          }
          statementAttached.current = true
        } catch (err) { failures.push(err.response?.data?.message ?? err.message ?? 'Could not attach the first statement.') }
      }
      if (failures.length) throw new Error(`The card was created. ${failures.join(' ')} Retry the remaining setup below; the card will not be created again.`)
      await refreshData()
      navigate(statementFile.current ? `/cards/${await resolveCardId(card.id)}?tab=statements` : '/cards')
    } catch (err) { setError(err.response?.data?.message ?? err.message ?? 'Failed to save card.') }
    finally { setRetrying(false) }
  }

  if (isEdit && initialValues?.permission === 'viewer') return <p>This shared card is read-only.</p>

  if (isEdit && !initialValues && error) return <div role="alert"><p>{error}</p><button onClick={() => window.location.reload()} className="mt-2 text-indigo-600">Retry</button></div>

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

      {!isEdit && !savedCard && <CardAnalysisUpload onAnalyzed={handleAnalyzed} />}

      {!savedCard && <CardForm
        existingCards={existingCards}
        initialValues={initialValues}
        onSubmit={handleSubmit}
        submitLabel={isEdit ? 'Save changes' : 'Add card'}
      />}

      {savedCard && <div className="space-y-2"><button disabled={retrying} onClick={() => handleSubmit(lastValues.current)} className="rounded bg-indigo-600 px-3 py-2 text-sm text-white">{retrying ? 'Retrying…' : 'Retry pending setup'}</button><button onClick={() => navigate(`/cards/${savedCard.id}?tab=statements`)} className="ml-3 text-sm text-indigo-600">Open created card</button></div>}
      {savedCard && error && statementFile.current && !statementAttached.current && <button disabled={retrying} onClick={async () => {
        setRetrying(true)
        try { const cardId = await resolveCardId(savedCard.id); setPreview(await previewStatement(statementFile.current, cardId)); setReviewed(null) }
        catch (err) { setError(err.response?.data?.message ?? err.message) }
        finally { setRetrying(false) }
      }} className="text-sm text-indigo-600">Analyze and review the statement again</button>}
      {!isEdit && preview && (!savedCard || !reviewed) && <StatementReview key={preview.preview_id} preview={preview} card={savedCard} onReviewChange={() => { setReviewed(null); attachmentPayload.current = null }} deferImport confirmLabel="Use reviewed statement" onConfirm={async (payload) => { attachmentPayload.current = null; setReviewed(payload); setError(null) }} onCancel={() => { setPreview(null); setReviewed(null); attachmentPayload.current = null }} />}
      {!isEdit && !savedCard && statementFile.current && !reviewed && <p role="status" className="text-sm text-gray-600">Your uploaded PDF will be saved in Statements when you add the card. You can complete its review now or from the Statements tab later.</p>}
      {reviewed && !savedCard && <p role="status" className="text-sm text-green-700">Statement reviewed. Save the card to attach the PDF and import the selected data.</p>}

      {!isEdit && !savedCard && suggestedBenefits.length > 0 && (
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
