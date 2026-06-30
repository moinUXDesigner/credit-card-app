import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { benefitSchema, BENEFIT_TYPES, BENEFIT_FREQUENCIES } from '../../schemas/benefitSchema'

export default function BenefitForm({ onSubmit, onCancel }) {
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm({
    resolver: zodResolver(benefitSchema),
    defaultValues: {
      type: 'lounge',
      title: '',
      frequency: 'quarterly',
      total_allowed: 1,
      expiry_date: '',
      estimated_value: '',
    },
  })

  const fieldClass = 'mt-1 w-full rounded border px-3 py-2'
  const labelClass = 'block text-sm font-medium text-gray-700'

  return (
    <form
      onSubmit={handleSubmit((values) => onSubmit({ ...values, expiry_date: values.expiry_date || null }))}
      className="space-y-4 rounded-lg border bg-white p-4"
    >
      <div className="grid grid-cols-2 gap-4">
        <div>
          <label className={labelClass}>Type</label>
          <select {...register('type')} className={fieldClass}>
            {BENEFIT_TYPES.map((t) => (
              <option key={t} value={t}>
                {t}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label className={labelClass}>Frequency</label>
          <select {...register('frequency')} className={fieldClass}>
            {BENEFIT_FREQUENCIES.map((f) => (
              <option key={f} value={f}>
                {f}
              </option>
            ))}
          </select>
        </div>
        <div className="col-span-2">
          <label className={labelClass}>Title</label>
          <input {...register('title')} className={fieldClass} />
          {errors.title && <p className="mt-1 text-xs text-red-600">{errors.title.message}</p>}
        </div>
        <div>
          <label className={labelClass}>Total allowed</label>
          <input type="number" min={1} {...register('total_allowed')} className={fieldClass} />
        </div>
        <div>
          <label className={labelClass}>Estimated value (₹)</label>
          <input type="number" step="0.01" {...register('estimated_value')} className={fieldClass} />
        </div>
        <div>
          <label className={labelClass}>Expiry date</label>
          <input type="date" {...register('expiry_date')} className={fieldClass} />
        </div>
      </div>
      <div className="flex gap-3">
        <button
          type="submit"
          disabled={isSubmitting}
          className="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700 disabled:opacity-50"
        >
          {isSubmitting ? 'Saving…' : 'Add benefit'}
        </button>
        <button type="button" onClick={onCancel} className="rounded border px-4 py-2 text-gray-700 hover:bg-gray-50">
          Cancel
        </button>
      </div>
    </form>
  )
}
