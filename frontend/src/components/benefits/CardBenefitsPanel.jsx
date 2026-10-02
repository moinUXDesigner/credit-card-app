import { useState } from 'react'
import { useBenefits } from '../../hooks/useBenefits'
import { createBenefit, deleteBenefit, markBenefitUsed } from '../../api/benefits'
import BenefitForm from './BenefitForm'
import BenefitListItem from './BenefitListItem'

export default function CardBenefitsPanel({ cardId, readOnly }) {
  const { benefits, refresh } = useBenefits(cardId)
  const [showForm, setShowForm] = useState(false)

  const handleAdd = async (values) => {
    await createBenefit(cardId, values)
    setShowForm(false)
    refresh()
  }

  const handleMarkUsed = async (id) => {
    await markBenefitUsed(id)
    refresh()
  }

  const handleDelete = async (id) => {
    if (!window.confirm('Delete this benefit?')) return
    await deleteBenefit(id)
    refresh()
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-sm font-semibold text-gray-900">Benefits</h2>
        {!readOnly && <button
          onClick={() => setShowForm((v) => !v)}
          className="rounded bg-indigo-600 px-3 py-2 text-sm text-white hover:bg-indigo-700"
        >
          {showForm ? 'Close' : 'Add Benefit'}
        </button>}
      </div>

      {showForm && <BenefitForm onSubmit={handleAdd} onCancel={() => setShowForm(false)} />}

      {benefits.length === 0 && <p className="text-sm text-gray-500">No benefits tracked for this card yet.</p>}

      <div className="space-y-3">
        {benefits.map((benefit) => (
          <BenefitListItem key={benefit.id} benefit={benefit} onMarkUsed={handleMarkUsed} onDelete={handleDelete} readOnly={readOnly} />
        ))}
      </div>
    </div>
  )
}
