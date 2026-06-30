import { z } from 'zod'

export const NETWORKS = ['visa', 'mastercard', 'rupay', 'amex']
export const CATEGORIES = ['fuel', 'grocery', 'amazon', 'dining', 'travel', 'utilities', 'online', 'other']

const optionalNullableNumber = z.preprocess(
  (val) => (val === '' || val === undefined ? null : val),
  z.coerce.number().min(0).nullable().optional(),
)

export const cardSchema = z.object({
  card_name: z.string().min(1, 'Required').max(100),
  bank_name: z.string().min(1, 'Required').max(100),
  last_four_digits: z.string().regex(/^\d{4}$/, 'Must be exactly 4 digits'),
  network: z.enum(NETWORKS),
  total_limit: z.coerce.number().min(0),
  current_outstanding: z.coerce.number().min(0),
  statement_day: z.coerce.number().int().min(1).max(31),
  due_day: z.coerce.number().int().min(1).max(31),
  annual_fee_amount: z.coerce.number().min(0),
  annual_fee_month: z.coerce.number().int().min(1).max(12),
  waiver_spend_required: z.coerce.number().min(0),
  waiver_spend_completed: z.coerce.number().min(0).optional(),
  card_year_start_month: z.coerce.number().int().min(1).max(12),
  reward_point_balance: z.coerce.number().min(0).optional(),
  reward_point_value_estimate: z.coerce.number().min(0).optional(),
  best_categories: z.array(z.enum(CATEGORIES)).optional(),
  reward_rate_general: optionalNullableNumber,
  cashback_cap_amount: optionalNullableNumber,
  lounge_access: z.boolean().optional(),
})
