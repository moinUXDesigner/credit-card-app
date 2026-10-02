import { useState } from 'react'
import ledger from '../../../DEVELOPMENT_LEDGER.md?raw'

// Read the checked-in ledger so the app and project documentation stay aligned.
function readTable(section) {
  const text = ledger.split(`## ${section}\n`)[1]?.split('\n## ')[0] ?? ''
  return text.split('\n').filter((line) => line.startsWith('| ')).slice(2)
    .map((line) => line.split('|').slice(1, -1).map((cell) => cell.trim().replaceAll('`', '')))
}

const features = readTable('Scope and delivery')
const tasks = readTable('Pending development and operational work')
const history = readTable('Session development history')
const isBuilt = (row) => row[2].startsWith('Built')
const filters = ['Scoped', 'Built', 'Pending']

export default function DevelopmentLedger() {
  const [filter, setFilter] = useState('Scoped')
  const [query, setQuery] = useState('')
  const counts = {
    Scoped: features.length,
    Built: features.filter(isBuilt).length,
    Pending: features.filter((row) => !isBuilt(row)).length,
  }
  const visible = features.filter((row) => (
    (filter === 'Scoped' || (filter === 'Built' ? isBuilt(row) : !isBuilt(row)))
    && row.join(' ').toLowerCase().includes(query.toLowerCase())
  ))

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-xl font-semibold text-gray-900">Development Ledger</h1>
        <p className="mt-1 text-sm text-gray-500">Updated 1 October 2026 · Project scope and delivery status</p>
      </div>
      <div className="grid gap-3 sm:grid-cols-3">
        {filters.map((label) => (
          <button key={label} type="button" onClick={() => setFilter(label)} aria-pressed={filter === label}
            className={`rounded-lg border p-4 text-left ${filter === label ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 bg-white'}`}>
            <span className="text-sm text-gray-600">{label}</span>
            <span className="mt-1 block text-3xl font-semibold text-gray-900">{counts[label]}</span>
            <span className="mt-1 block text-xs text-gray-500">
              {label === 'Scoped' ? 'All tracked features, including deferred scope' : label === 'Built' ? 'Implementation exists' : 'Unbuilt features, including deferred scope'}
            </span>
          </button>
        ))}
      </div>
      <p className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
        Built means code exists. Backend and browser regression checks are recorded in the ledger. Live AI configuration, reminder delivery, and hosted rollout still require operational verification.
      </p>
      <section className="space-y-3" aria-labelledby="feature-ledger-title">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <h2 id="feature-ledger-title" className="font-semibold text-gray-900">{filter} features</h2>
          <label className="w-full text-sm text-gray-600 sm:w-auto">Search ledger
            <input type="search" value={query} onChange={(event) => setQuery(event.target.value)}
              placeholder="Feature, status, or task" className="mt-1 block min-w-0 w-full rounded border bg-white px-3 py-2 sm:mt-0 sm:ml-2 sm:inline-block sm:w-auto" />
          </label>
        </div>
        <div className="overflow-x-auto rounded-lg border bg-white">
          <table className="w-full text-left text-sm">
            <thead className="bg-gray-50 text-gray-600"><tr>
              {['ID', 'Scope', 'Status', 'Built evidence', 'Pending work'].map((title) => <th key={title} scope="col" className="px-4 py-3">{title}</th>)}
            </tr></thead>
            <tbody className="divide-y">
              {visible.map(([id, scope, status, evidence, pending]) => (
                <tr key={id} className="align-top">
                  <td className="whitespace-nowrap px-4 py-3 text-gray-500">{id}</td>
                  <th scope="row" className="min-w-40 px-4 py-3 font-medium text-gray-900">{scope}</th>
                  <td className="min-w-32 px-4 py-3"><span className={`inline-block rounded px-2 py-1 text-xs ${status.startsWith('Built') ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800'}`}>{status}</span></td>
                  <td className="min-w-48 px-4 py-3 text-gray-600">{evidence}</td>
                  <td className="min-w-56 px-4 py-3 text-gray-600">{pending}</td>
                </tr>
              ))}
              {visible.length === 0 && <tr><td colSpan={5} className="p-6 text-center text-gray-500">No features match your search.</td></tr>}
            </tbody>
          </table>
        </div>
      </section>
      <section aria-labelledby="pending-tasks-title">
        <h2 id="pending-tasks-title" className="mb-3 font-semibold text-gray-900">Pending development and operations</h2>
        <div className="grid gap-3 lg:grid-cols-2">
          {tasks.map(([priority, title, completion]) => (
            <article key={title} className="rounded-lg border bg-white p-4">
              <span className="text-xs font-medium text-amber-700">{priority} priority</span>
              <h3 className="mt-1 font-medium text-gray-900">{title}</h3>
              <p className="mt-2 text-sm text-gray-600">{completion}</p>
            </article>
          ))}
        </div>
      </section>
      <section aria-labelledby="ledger-history-title">
        <h2 id="ledger-history-title" className="mb-3 font-semibold text-gray-900">Development history</h2>
        <ul className="divide-y rounded-lg border bg-white px-4">
          {history.map(([date, change, result]) => <li key={change} className="py-3">
            <p className="text-xs text-gray-500">{date}</p>
            <p className="mt-1 text-sm font-medium text-gray-900">{change}</p>
            <p className="mt-1 text-sm text-gray-600">{result}</p>
          </li>)}
        </ul>
      </section>
    </div>
  )
}
