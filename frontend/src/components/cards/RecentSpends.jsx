export default function RecentSpends() {
  return <section aria-label="Recent spends" className="mt-4 border-t pt-4">
    <div className="flex flex-wrap items-center justify-between gap-2">
      <h2 className="text-sm font-medium text-gray-900">Recent spends</h2>
      <p className="text-sm text-gray-600">Total <span className="font-medium" aria-label="Recent spends total unavailable">—</span></p>
    </div>
    <p className="mt-2 text-sm text-gray-500">Recent spends will appear here once connected.</p>
  </section>
}
