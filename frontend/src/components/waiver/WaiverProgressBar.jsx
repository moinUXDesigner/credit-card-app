export default function WaiverProgressBar({ completed, required, remaining, monthsLeft, suggestedMonthlySpend }) {
  if (!required || required <= 0) {
    return <p className="text-xs text-gray-500">No annual fee waiver target on this card.</p>
  }

  const progress = Math.min(100, (completed / required) * 100)
  const met = remaining <= 0

  return (
    <div>
      <div className="flex items-center justify-between text-xs">
        <span className="text-gray-600">Waiver progress</span>
        <span className="font-medium text-indigo-700">
          ₹{completed.toLocaleString('en-IN')} / ₹{required.toLocaleString('en-IN')}
        </span>
      </div>
      <div className="mt-1 h-2 w-full rounded-full bg-gray-200">
        <div className="h-2 rounded-full bg-indigo-500" style={{ width: `${progress}%` }} />
      </div>
      <p className="mt-1 text-xs text-gray-600">
        {met
          ? 'Waiver target met.'
          : `₹${remaining.toLocaleString('en-IN')} remaining · ${monthsLeft} month${monthsLeft === 1 ? '' : 's'} left · suggested ₹${suggestedMonthlySpend.toLocaleString('en-IN')}/month`}
      </p>
    </div>
  )
}
