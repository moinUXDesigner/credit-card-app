import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { cardSchema, NETWORKS, CATEGORIES } from '../../schemas/cardSchema'

const DEFAULTS = {
  card_name: '',
  bank_name: '',
  last_four_digits: '',
  network: 'visa',
  total_limit: '',
  shared_limit_group: '',
  current_outstanding: '0',
  statement_day: '1',
  due_day: '15',
  annual_fee_amount: '0',
  annual_fee_month: '1',
  waiver_spend_required: '0',
  waiver_spend_completed: '0',
  card_year_start_month: '1',
  reward_point_balance: '0',
  reward_point_value_estimate: '0',
  best_categories: [],
  reward_rate_general: '',
  cashback_cap_amount: '',
  forex_markup_percent: '',
  fuel_surcharge_waiver_percent: '',
  insurance_cover_amount: '',
  lounge_access: false,
}

export default function CardForm({ initialValues, onSubmit, submitLabel = 'Save' }) {
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm({
    resolver: zodResolver(cardSchema),
    defaultValues: { ...DEFAULTS, ...initialValues },
  })

  const fieldClass = 'mt-1 min-w-0 w-full rounded border px-3 py-2'
  const labelClass = 'block text-sm font-medium text-gray-700'
  const errorClass = 'mt-1 text-xs text-red-600'

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-6 rounded-lg border bg-white p-4 shadow-sm sm:p-6">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <label className={labelClass}>Card name</label>
          <input {...register('card_name')} className={fieldClass} />
          {errors.card_name && <p className={errorClass}>{errors.card_name.message}</p>}
        </div>
        <div>
          <label className={labelClass}>Bank name</label>
          <input {...register('bank_name')} className={fieldClass} />
          {errors.bank_name && <p className={errorClass}>{errors.bank_name.message}</p>}
        </div>
        <div>
          <label className={labelClass}>Last 4 digits</label>
          <input {...register('last_four_digits')} maxLength={4} className={fieldClass} />
          {errors.last_four_digits && <p className={errorClass}>{errors.last_four_digits.message}</p>}
        </div>
        <div>
          <label className={labelClass}>Network</label>
          <select {...register('network')} className={fieldClass}>
            {NETWORKS.map((n) => (
              <option key={n} value={n}>
                {n}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label className={labelClass}>Total limit (₹)</label>
          <input type="number" step="0.01" {...register('total_limit')} className={fieldClass} />
          {errors.total_limit && <p className={errorClass}>{errors.total_limit.message}</p>}
        </div>
        <div>
          <label className={labelClass}>Shared limit group (optional)</label>
          <input
            {...register('shared_limit_group')}
            placeholder="e.g. hdfc-combined"
            className={fieldClass}
          />
          <p className="mt-1 text-xs text-gray-500">
            Set the same value on every card that pools one combined limit with this bank (e.g. two HDFC cards
            sharing one credit line). Total limit must match across all cards in the group.
          </p>
          {errors.shared_limit_group && <p className={errorClass}>{errors.shared_limit_group.message}</p>}
        </div>
        <div>
          <label className={labelClass}>Current outstanding (₹)</label>
          <input type="number" step="0.01" {...register('current_outstanding')} className={fieldClass} />
        </div>
        <div>
          <label className={labelClass}>Statement day</label>
          <input type="number" min={1} max={31} {...register('statement_day')} className={fieldClass} />
          {errors.statement_day && <p className={errorClass}>{errors.statement_day.message}</p>}
        </div>
        <div>
          <label className={labelClass}>Due day</label>
          <input type="number" min={1} max={31} {...register('due_day')} className={fieldClass} />
          {errors.due_day && <p className={errorClass}>{errors.due_day.message}</p>}
        </div>
        <div>
          <label className={labelClass}>Annual fee (₹)</label>
          <input type="number" step="0.01" {...register('annual_fee_amount')} className={fieldClass} />
        </div>
        <div>
          <label className={labelClass}>Annual fee month</label>
          <input type="number" min={1} max={12} {...register('annual_fee_month')} className={fieldClass} />
        </div>
        <div>
          <label className={labelClass}>Waiver spend required (₹)</label>
          <input type="number" step="0.01" {...register('waiver_spend_required')} className={fieldClass} />
        </div>
        <div>
          <label className={labelClass}>Waiver spend completed (₹)</label>
          <input type="number" step="0.01" {...register('waiver_spend_completed')} className={fieldClass} />
        </div>
        <div>
          <label className={labelClass}>Card year start month</label>
          <input type="number" min={1} max={12} {...register('card_year_start_month')} className={fieldClass} />
        </div>
        <div>
          <label className={labelClass}>Reward rate (general, %)</label>
          <input type="number" step="0.01" {...register('reward_rate_general')} className={fieldClass} />
        </div>
        <div>
          <label className={labelClass}>Cashback cap (₹)</label>
          <input type="number" step="0.01" {...register('cashback_cap_amount')} className={fieldClass} />
        </div>
        <div>
          <label className={labelClass}>Reward point balance</label>
          <input type="number" step="0.01" {...register('reward_point_balance')} className={fieldClass} />
        </div>
        <div>
          <label className={labelClass}>Reward point value (₹/point)</label>
          <input type="number" step="0.0001" {...register('reward_point_value_estimate')} className={fieldClass} />
        </div>
        <div>
          <label className={labelClass}>Forex markup (%)</label>
          <input type="number" step="0.01" {...register('forex_markup_percent')} className={fieldClass} />
          {errors.forex_markup_percent && <p className={errorClass}>{errors.forex_markup_percent.message}</p>}
        </div>
        <div>
          <label className={labelClass}>Fuel surcharge waiver (%)</label>
          <input type="number" step="0.01" {...register('fuel_surcharge_waiver_percent')} className={fieldClass} />
          {errors.fuel_surcharge_waiver_percent && (
            <p className={errorClass}>{errors.fuel_surcharge_waiver_percent.message}</p>
          )}
        </div>
        <div>
          <label className={labelClass}>Insurance cover amount (₹)</label>
          <input type="number" step="0.01" {...register('insurance_cover_amount')} className={fieldClass} />
        </div>
      </div>

      <div>
        <label className={labelClass}>Best categories</label>
        <div className="mt-2 flex flex-wrap gap-3">
          {CATEGORIES.map((c) => (
            <label key={c} className="flex items-center gap-1 text-sm text-gray-700">
              <input type="checkbox" value={c} {...register('best_categories')} />
              {c}
            </label>
          ))}
        </div>
      </div>

      <label className="flex items-center gap-2 text-sm text-gray-700">
        <input type="checkbox" {...register('lounge_access')} />
        Lounge access
      </label>

      <button
        type="submit"
        disabled={isSubmitting}
        className="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700 disabled:opacity-50"
      >
        {isSubmitting ? 'Saving…' : submitLabel}
      </button>
    </form>
  )
}
