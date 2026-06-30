import { z } from 'zod'

export const BENEFIT_TYPES = ['lounge', 'cashback', 'reward_points', 'dining', 'movie', 'other']
export const BENEFIT_FREQUENCIES = ['monthly', 'quarterly', 'yearly', 'one_time']

export const benefitSchema = z.object({
  type: z.enum(BENEFIT_TYPES),
  title: z.string().min(1, 'Required').max(150),
  frequency: z.enum(BENEFIT_FREQUENCIES),
  total_allowed: z.coerce.number().int().min(1),
  expiry_date: z.string().optional(),
  estimated_value: z.coerce.number().min(0).optional(),
})
