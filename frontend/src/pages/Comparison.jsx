import { useEffect, useState } from 'react'
import { useCards } from '../hooks/useCards'
import { getComparison } from '../api/comparison'

const ROWS = [
  { key: 'bank_name', label: 'Bank' },
  { key: 'network', label: 'Network' },
  { key: 'total_limit', label: 'Total limit', money: true },
  { key: 'current_outstanding', label: 'Outstanding', money: true },
  { key: 'utilization_percentage', label: 'Utilization', suffix: '%' },
  { key: 'annual_fee_amount', label: 'Annual fee', money: true },
  { key: 'waiver_progress_percentage', label: 'Waiver progress', suffix: '%' },
  { key: 'reward_rate_general', label: 'Reward rate', suffix: '%' },
  { key: 'cashback_cap_amount', label: 'Cashback cap', money: true },
  { key: 'lounge_access', label: 'Lounge access', bool: true },
  { key: 'best_categories', label: 'Best categories', list: true },
]

function formatValue(card, row) {
  const value = card[row.key]
  if (value === null || value === undefined) return '—'
  if (row.bool) return value ? 'Yes' : 'No'
  if (row.list) return Array.isArray(value) && value.length ? value.join(', ') : '—'
  if (row.money) return `₹${Number(value).toLocaleString('en-IN')}`
  if (row.suffix) return `${value}${row.suffix}`
  return value
}

export default function Comparison() {
  const { cards } = useCards()
  const [selectedIds, setSelectedIds] = useState([])
  const [compared, setCompared] = useState([])

  useEffect(() => {
    if (selectedIds.length === 0) {
      setCompared([])
      return
    }
    getComparison(selectedIds).then(setCompared)
  }, [selectedIds])

  const toggle = (id) => {
    setSelectedIds((ids) => (ids.includes(id) ? ids.filter((x) => x !== id) : [...ids, id]))
  }

  return (
    <div className="space-y-4">
      <h1 className="text-lg font-semibold text-gray-900">Comparison</h1>

      <div className="flex flex-wrap gap-3">
        {cards.map((card) => (
          <label key={card.id} className="flex items-center gap-1 text-sm text-gray-700">
            <input type="checkbox" checked={selectedIds.includes(card.id)} onChange={() => toggle(card.id)} />
            {card.card_name}
          </label>
        ))}
      </div>

      {compared.length === 0 ? (
        <p className="text-sm text-gray-500">Select two or more cards to compare.</p>
      ) : (
        <div className="overflow-x-auto rounded-lg border bg-white shadow-sm">
          <table className="min-w-full text-sm [&_td]:min-w-36 [&_th]:min-w-36">
            <thead>
              <tr className="border-b bg-gray-50">
                <th className="px-4 py-2 text-left font-medium text-gray-600"></th>
                {compared.map((card) => (
                  <th key={card.id} className="px-4 py-2 text-left font-medium text-gray-900">
                    {card.card_name}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {ROWS.map((row) => (
                <tr key={row.key} className="border-b last:border-0">
                  <td className="px-4 py-2 text-gray-600">{row.label}</td>
                  {compared.map((card) => (
                    <td key={card.id} className="px-4 py-2 text-gray-900">
                      {formatValue(card, row)}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
