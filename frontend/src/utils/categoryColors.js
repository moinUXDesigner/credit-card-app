// Fixed categorical palette (light-mode, validated via the dataviz skill's
// validate_palette.js — 8 hues, adjacent-pair CVD-safe). Colors are assigned
// to a category by identity, never by chart rank, so a category keeps the
// same color across refreshes/periods.
const CATEGORY_COLORS = {
  fuel: '#2a78d6', // blue
  grocery: '#008300', // green
  dining: '#e87ba4', // magenta
  travel: '#eda100', // yellow
  utilities: '#1baf7a', // aqua
  online: '#eb6834', // orange
  amazon: '#4a3aa7', // violet
  other: '#e34948', // red
}

// Categories beyond the 8-hue budget (and any unrecognized legacy freeform
// values) share this neutral fallback rather than a manufactured 9th hue.
const FALLBACK_COLOR = '#898781'

const MAX_SLICES = 8

export function getCategoryColor(category) {
  return CATEGORY_COLORS[category] ?? FALLBACK_COLOR
}

export function getCategoryLabel(category) {
  if (category === 'uncategorized') return 'Uncategorized'
  return category.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

// Cap the chart/grid at 8 slices — beyond that, fold the smallest remainder
// into a single "other" bucket rather than reusing a hue for a distinct
// identity.
export function prepareCategories(categories) {
  if (categories.length <= MAX_SLICES) return categories

  const visible = categories.slice(0, MAX_SLICES - 1)
  const overflow = categories.slice(MAX_SLICES - 1)
  const overflowAmount = overflow.reduce((sum, c) => sum + c.amount, 0)
  const overflowPercentage = overflow.reduce((sum, c) => sum + c.percentage, 0)

  const existingOther = visible.find((c) => c.category === 'other')
  if (existingOther) {
    existingOther.amount += overflowAmount
    existingOther.percentage += overflowPercentage
    return visible
  }

  return [...visible, { category: 'other', amount: overflowAmount, percentage: overflowPercentage }]
}
