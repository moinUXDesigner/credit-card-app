import { useMemo, useState } from 'react'
import { useCards } from '../hooks/useCards'
import Skeleton from '../components/common/Skeleton'
import Badge from '../components/common/Badge'
import { getBankLogo } from '../lib/bankLogos'

const MONTH_NAMES = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
]
const WEEKDAY_NAMES = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

function daysInMonth(year, month) {
  return new Date(year, month, 0).getDate()
}

// Recurring day-of-month clamped to the shorter month (e.g. day 31 in Feb -> 28/29),
// mirroring the same clamping the backend's DateOccurrenceService applies.
function clampedDay(day, totalDaysInMonth) {
  return Math.min(day, totalDaysInMonth)
}

function buildMonthCells(year, month) {
  const totalDays = daysInMonth(year, month)
  const leadingBlanks = new Date(year, month - 1, 1).getDay()
  const cells = []
  for (let i = 0; i < leadingBlanks; i++) cells.push(null)
  for (let day = 1; day <= totalDays; day++) cells.push(day)
  while (cells.length % 7 !== 0) cells.push(null)
  return cells
}

function CalendarCardSkeleton() {
  return (
    <div className="rounded-lg border bg-white p-4 shadow-sm">
      <Skeleton className="h-5 w-48" />
      <div className="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-3">
        {Array.from({ length: 3 }).map((_, i) => (
          <div key={i}>
            <Skeleton className="h-3 w-20" />
            <Skeleton className="mt-1 h-4 w-28" />
          </div>
        ))}
      </div>
    </div>
  )
}

function CalendarGridSkeleton() {
  return (
    <div className="rounded-lg border bg-white p-4 shadow-sm">
      <Skeleton className="mb-3 h-6 w-40" />
      <div className="grid grid-cols-7 gap-1">
        {Array.from({ length: 35 }).map((_, i) => (
          <Skeleton key={i} className="h-16 w-full" />
        ))}
      </div>
    </div>
  )
}

function EventBadge({ label, card, colorClass }) {
  const logoUrl = getBankLogo(card.bank_name)

  return (
    <div className={`flex items-center gap-1 rounded px-1 py-0.5 text-[10px] font-medium ${colorClass}`}>
      <span className="min-w-0 flex-1 truncate" title={`${label} · ${card.card_name} · ${card.last_four_digits}`}>
        {label}<span className="sr-only sm:not-sr-only">-{card.last_four_digits}</span>
      </span>
      {logoUrl && (
        <img
          src={logoUrl}
          alt={card.bank_name}
          title={card.bank_name}
          className="hidden h-3 w-4 shrink-0 object-contain sm:block"
          onError={(e) => {
            e.currentTarget.style.display = 'none'
          }}
        />
      )}
    </div>
  )
}

function DayCell({ day, isToday, isSelected, statementCards, dueCards, feeCards, onSelect }) {
  if (day === null) return <div className="min-h-20 rounded border border-transparent bg-gray-50" />

  const hasEvents = statementCards.length > 0 || dueCards.length > 0 || feeCards.length > 0

  return (
    <button
      type="button"
      onClick={() => onSelect(day)}
      aria-label={`Day ${day}${isToday ? ', today' : ''}${hasEvents ? `, ${[
        ...statementCards.map((c) => `statement for ${c.card_name}, ending in ${c.last_four_digits}`),
        ...dueCards.map((c) => `payment due for ${c.card_name}, ending in ${c.last_four_digits}`),
        ...feeCards.map((c) => `annual fee for ${c.card_name}, ending in ${c.last_four_digits}`),
      ].join('; ')}` : ''}`}
      aria-pressed={isSelected}
      className={`flex min-h-20 min-w-0 flex-col rounded border p-1 text-left sm:p-1.5 ${
        isSelected ? 'border-indigo-500 ring-1 ring-indigo-500' : 'border-gray-200 hover:border-gray-300'
      } ${isToday ? 'bg-indigo-50' : 'bg-white'}`}
    >
      <div className={`text-xs font-medium ${isToday ? 'text-indigo-700' : 'text-gray-700'}`}>{day}</div>
      {hasEvents && (
        <div className="mt-1 w-full space-y-0.5">
          {statementCards.map((c) => (
            <EventBadge key={`stmt-${c.id}`} label="Stmt" card={c} colorClass="bg-indigo-100 text-indigo-700" />
          ))}
          {dueCards.map((c) => (
            <EventBadge key={`due-${c.id}`} label="Due" card={c} colorClass="bg-red-100 text-red-700" />
          ))}
          {feeCards.map((c) => (
            <EventBadge key={`fee-${c.id}`} label="Fee" card={c} colorClass="bg-yellow-100 text-yellow-700" />
          ))}
        </div>
      )}
    </button>
  )
}

function CalendarGrid({ cards, cursor, onCursorChange }) {
  const today = new Date()
  const totalDays = daysInMonth(cursor.year, cursor.month)
  const cells = useMemo(() => buildMonthCells(cursor.year, cursor.month), [cursor.year, cursor.month])
  const [selectedDay, setSelectedDay] = useState(null)

  const eventsForDay = (day) => ({
    statementCards: cards.filter((c) => clampedDay(c.statement_day, totalDays) === day),
    dueCards: cards.filter((c) => clampedDay(c.due_day, totalDays) === day),
    feeCards: day === 1 ? cards.filter((c) => c.annual_fee_month === cursor.month) : [],
  })

  const goToMonth = (delta) => {
    setSelectedDay(null)
    let month = cursor.month + delta
    let year = cursor.year
    if (month < 1) {
      month = 12
      year -= 1
    } else if (month > 12) {
      month = 1
      year += 1
    }
    onCursorChange({ year, month })
  }

  const goToToday = () => {
    setSelectedDay(null)
    onCursorChange({ year: today.getFullYear(), month: today.getMonth() + 1 })
  }

  const selected = selectedDay ? eventsForDay(selectedDay) : null

  return (
    <div className="rounded-lg border bg-white p-2 shadow-sm sm:p-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-base font-semibold text-gray-900">
          {MONTH_NAMES[cursor.month - 1]} {cursor.year}
        </h2>
        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={() => goToMonth(-1)}
            className="rounded border px-2 py-1 text-sm text-gray-700 hover:bg-gray-50"
            aria-label="Previous month"
          >
            ←
          </button>
          <button
            type="button"
            onClick={goToToday}
            className="rounded border px-2 py-1 text-sm text-gray-700 hover:bg-gray-50"
          >
            Today
          </button>
          <button
            type="button"
            onClick={() => goToMonth(1)}
            className="rounded border px-2 py-1 text-sm text-gray-700 hover:bg-gray-50"
            aria-label="Next month"
          >
            →
          </button>
        </div>
      </div>

      <div className="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-600">
        <span className="flex items-center gap-1">
          <span className="h-2.5 w-2.5 rounded-full bg-indigo-400" /> Statement date
        </span>
        <span className="flex items-center gap-1">
          <span className="h-2.5 w-2.5 rounded-full bg-red-400" /> Due date
        </span>
        <span className="flex items-center gap-1">
          <span className="h-2.5 w-2.5 rounded-full bg-yellow-400" /> Annual fee month
        </span>
      </div>

      <div className="mt-2 grid grid-cols-7 gap-1 text-center text-xs font-medium text-gray-500">
        {WEEKDAY_NAMES.map((w) => (
          <div key={w}>{w}</div>
        ))}
      </div>
      <div className="mt-1 grid grid-cols-7 gap-1">
        {cells.map((day, i) => {
          if (day === null) return <DayCell key={i} day={null} onSelect={() => {}} />
          const isToday =
            cursor.year === today.getFullYear() && cursor.month === today.getMonth() + 1 && day === today.getDate()
          const { statementCards, dueCards, feeCards } = eventsForDay(day)
          return (
            <DayCell
              key={i}
              day={day}
              isToday={isToday}
              isSelected={selectedDay === day}
              statementCards={statementCards}
              dueCards={dueCards}
              feeCards={feeCards}
              onSelect={setSelectedDay}
            />
          )
        })}
      </div>

      {selected && (
        <div className="mt-4 rounded border bg-gray-50 p-3 text-sm">
          <p className="mb-2 font-medium text-gray-900">
            {MONTH_NAMES[cursor.month - 1]} {selectedDay}, {cursor.year}
          </p>
          {selected.statementCards.length === 0 && selected.dueCards.length === 0 && selected.feeCards.length === 0 ? (
            <p className="text-gray-500">No card events on this day.</p>
          ) : (
            <ul className="space-y-1">
              {selected.statementCards.map((c) => (
                <li key={`stmt-${c.id}`} className="flex items-center gap-2">
                  <Badge color="indigo">Statement</Badge>
                  <span className="text-gray-700">
                    {c.card_name} ({c.bank_name}) · •••• {c.last_four_digits}
                  </span>
                </li>
              ))}
              {selected.dueCards.map((c) => (
                <li key={`due-${c.id}`} className="flex items-center gap-2">
                  <Badge color="red">Due</Badge>
                  <span className="text-gray-700">
                    {c.card_name} ({c.bank_name}) · •••• {c.last_four_digits}
                  </span>
                </li>
              ))}
              {selected.feeCards.map((c) => (
                <li key={`fee-${c.id}`} className="flex items-center gap-2">
                  <Badge color="yellow">Annual fee</Badge>
                  <span className="text-gray-700">
                    {c.card_name} ({c.bank_name}) · •••• {c.last_four_digits}
                  </span>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  )
}

export default function CalendarPage() {
  const { cards, loading } = useCards()
  const [view, setView] = useState('calendar')
  const [cursor, setCursor] = useState(() => {
    const now = new Date()
    return { year: now.getFullYear(), month: now.getMonth() + 1 }
  })

  if (loading) {
    return (
      <div className="space-y-4">
        <h1 className="text-lg font-semibold text-gray-900">Calendar</h1>
        {view === 'calendar' ? (
          <CalendarGridSkeleton />
        ) : (
          <div className="space-y-3">
            {Array.from({ length: 3 }).map((_, i) => (
              <CalendarCardSkeleton key={i} />
            ))}
          </div>
        )}
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-lg font-semibold text-gray-900">Calendar</h1>
        <div className="flex rounded border text-sm">
          <button
            type="button"
            onClick={() => setView('calendar')}
            className={`rounded-l px-3 py-1.5 ${view === 'calendar' ? 'bg-indigo-600 text-white' : 'text-gray-700 hover:bg-gray-50'}`}
          >
            Calendar
          </button>
          <button
            type="button"
            onClick={() => setView('list')}
            className={`rounded-r px-3 py-1.5 ${view === 'list' ? 'bg-indigo-600 text-white' : 'text-gray-700 hover:bg-gray-50'}`}
          >
            List
          </button>
        </div>
      </div>

      {cards.length === 0 && <p className="text-sm text-gray-500">Add a card to see its key dates here.</p>}

      {cards.length > 0 && view === 'calendar' && (
        <CalendarGrid cards={cards} cursor={cursor} onCursorChange={setCursor} />
      )}

      {cards.length > 0 && view === 'list' && (
        <div className="space-y-3">
          {cards.map((card) => (
            <div key={card.id} className="rounded-lg border bg-white p-4 shadow-sm">
              <h2 className="font-medium text-gray-900">
                {card.card_name} <span className="text-sm text-gray-500">({card.bank_name})</span>
              </h2>
              <dl className="mt-2 grid grid-cols-1 gap-3 text-sm sm:grid-cols-3 sm:gap-4">
                <div>
                  <dt className="text-gray-500">Statement date</dt>
                  <dd className="text-gray-900">Day {card.statement_day} of every month</dd>
                </div>
                <div>
                  <dt className="text-gray-500">Due date</dt>
                  <dd className="text-gray-900">Day {card.due_day} of every month</dd>
                </div>
                <div>
                  <dt className="text-gray-500">Annual fee month</dt>
                  <dd className="text-gray-900">{MONTH_NAMES[card.annual_fee_month - 1]}</dd>
                </div>
              </dl>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
