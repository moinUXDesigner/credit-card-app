import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import CardForm from '../components/cards/CardForm'
import { createCard, getCard, updateCard } from '../api/cards'
import Skeleton from '../components/common/Skeleton'

function CardFormSkeleton() {
  return (
    <div className="space-y-6 rounded-lg border bg-white p-6 shadow-sm">
      <div className="grid grid-cols-2 gap-4">
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
  const [error, setError] = useState(null)
  const navigate = useNavigate()

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
      })
    })
  }, [id, isEdit])

  const handleSubmit = async (values) => {
    setError(null)
    try {
      if (isEdit) {
        await updateCard(id, values)
      } else {
        await createCard(values)
      }
      navigate('/cards')
    } catch (err) {
      setError(err.response?.data?.message ?? 'Failed to save card.')
    }
  }

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
      <CardForm initialValues={initialValues} onSubmit={handleSubmit} submitLabel={isEdit ? 'Save changes' : 'Add card'} />
    </div>
  )
}
