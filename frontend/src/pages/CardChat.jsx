import { useCallback, useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { listCards } from '../api/cards'
import { listStatements } from '../api/statements'
import { createConversation, deleteConversation, getConversation, getAiRequest, listConversations, sendChatMessage } from '../api/chat'
import { waitForAi } from '../api/aiPolling'

const prompts = ['Which card should I use for fuel?', 'How much did I spend on groceries this month?', 'Explain my outstanding balances and utilization.', 'What are the current benefits of my card?']
const failure = (error) => error.response?.data?.message ?? error.message ?? 'Could not load Card Chat.'

function SourceLink({ source }) {
  if (source.url?.startsWith('/cards/')) return <Link to={source.url} className="rounded border px-2 py-1 text-xs text-indigo-700 hover:bg-indigo-50">{source.title}</Link>
  if (source.url?.startsWith('https://')) return <a href={source.url} target="_blank" rel="noopener noreferrer" className="rounded border px-2 py-1 text-xs text-indigo-700 hover:bg-indigo-50">{source.title}{source.checked_at && <span className="ml-1 text-gray-500">· issuer source</span>}</a>
  return null
}

export default function CardChat() {
  const [cards, setCards] = useState([])
  const [statements, setStatements] = useState([])
  const [history, setHistory] = useState([])
  const [page, setPage] = useState(1)
  const [hasMore, setHasMore] = useState(false)
  const [cardId, setCardId] = useState('')
  const [statementId, setStatementId] = useState('')
  const [conversation, setConversation] = useState(null)
  const [draft, setDraft] = useState('')
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [online, setOnline] = useState(navigator.onLine)
  const [pollVersion, setPollVersion] = useState(0)
  const [restricted, setRestricted] = useState(false)
  const activeId = useRef(null)
  const attempt = useRef(null)
  const generation = useRef(0)
  const scroll = useRef(null)
  const nearBottom = useRef(true)
  const pending = conversation?.messages?.find((message) => message.status === 'processing')

  const loadHistory = useCallback(async () => {
    const result = await listConversations(page)
    setHistory(result.data ?? [])
    setHasMore(Boolean(result.next_page_url))
  }, [page])
  useEffect(() => { loadHistory().catch((err) => setError(failure(err))) }, [loadHistory])
  useEffect(() => {
    listCards().then(setCards).catch(() => {})
    const changed = () => setOnline(navigator.onLine)
    window.addEventListener('online', changed); window.addEventListener('offline', changed)
    return () => { window.removeEventListener('online', changed); window.removeEventListener('offline', changed) }
  }, [])
  useEffect(() => {
    let canceled = false
    if (conversation) return
    setStatements([]); setStatementId('')
    const candidates = cardId ? cards.filter((card) => String(card.id) === cardId) : cards
    Promise.all(candidates.map((card) => listStatements(card.id).then((rows) => rows.map((row) => ({ ...row, card_name: card.card_name }))).catch(() => [])))
      .then((results) => { if (!canceled) setStatements(results.flat()) })
    return () => { canceled = true }
  }, [cards, cardId, conversation])
  useEffect(() => {
    if (!pending?.request_id || !online) return
    const controller = new AbortController()
    const id = conversation.id
    waitForAi({ status: 'processing' }, () => getAiRequest(pending.request_id), { signal: controller.signal })
      .then(async () => {
        const current = await getConversation(id)
        if (activeId.current !== id || controller.signal.aborted) return
        setConversation(current)
        const completed = current.messages.find((message) => message.id === pending.id)
        if (completed?.status === 'ready') { setDraft(''); attempt.current = null; setError(null) }
        else setError(completed?.content ?? 'Could not answer. Retry the message.')
        await loadHistory()
      }).catch((err) => {
        if (err.name !== 'AbortError' && activeId.current === id) {
          if ([403, 404].includes(err.response?.status)) { setConversation(null); setRestricted(true) }
          setError(failure(err))
        }
      })
    return () => controller.abort()
  }, [conversation?.id, pending?.id, pending?.request_id, online, loadHistory, pollVersion])
  useEffect(() => {
    if (nearBottom.current && scroll.current) scroll.current.scrollTop = scroll.current.scrollHeight
  }, [conversation?.messages])

  const open = async (id) => {
    const version = ++generation.current
    activeId.current = id
    setLoading(true); setError(null); setRestricted(false); setConversation(null); setDraft(''); attempt.current = null; nearBottom.current = true
    try {
      const value = await getConversation(id)
      if (version === generation.current) { setConversation(value); setCardId(value.card_id ? String(value.card_id) : ''); setStatementId(value.statement_id ? String(value.statement_id) : '') }
    } catch (err) { if (version === generation.current) { setRestricted(true); setError(failure(err)) } }
    finally { if (version === generation.current) setLoading(false) }
  }
  const newChat = () => {
    generation.current++; activeId.current = null; attempt.current = null
    setConversation(null); setCardId(''); setStatementId(''); setDraft(''); setError(null); setRestricted(false); setLoading(false)
  }
  const submit = async (event, retryMessage) => {
    event?.preventDefault()
    const content = retryMessage?.content ?? draft.trim()
    if (!content || submitting || pending || !online) return
    setSubmitting(true); setError(null)
    try {
      let current = conversation
      if (!current) {
        current = await createConversation({ card_id: cardId ? Number(cardId) : null, statement_id: statementId ? Number(statementId) : null })
        activeId.current = current.id; setConversation({ ...current, messages: [] })
      }
      if (retryMessage) attempt.current = { conversationId: current.id, content, client_id: retryMessage.client_id }
      else if (attempt.current?.conversationId !== current.id || attempt.current.content !== content) attempt.current = { conversationId: current.id, content, client_id: crypto.randomUUID() }
      await sendChatMessage(current.id, { client_id: attempt.current.client_id, content })
      const value = await getConversation(current.id)
      if (activeId.current === current.id) { setConversation(value); nearBottom.current = true }
      await loadHistory()
    } catch (err) { setError(failure(err)) }
    finally { setSubmitting(false) }
  }
  const remove = async (id) => {
    if (!window.confirm('Delete this conversation and its messages?')) return
    try { await deleteConversation(id); if (activeId.current === id) newChat(); await loadHistory() }
    catch (err) { setError(failure(err)) }
  }

  return <div className="space-y-4">
    <div><h1 className="text-lg font-semibold text-gray-900">Card Chat</h1><p className="mt-1 text-sm text-gray-500">Ask about your cards and statements, or research current issuer benefits.</p></div>
    <div className="grid min-w-0 gap-4 lg:grid-cols-[220px_minmax(0,1fr)]">
      <aside className="space-y-3 rounded-xl border bg-white p-3">
        <button onClick={newChat} disabled={submitting} className="w-full rounded-lg bg-indigo-600 px-3 py-2 text-sm text-white disabled:opacity-50">New chat</button>
        <h2 className="text-xs font-semibold uppercase tracking-wide text-gray-500">Conversations</h2>
        {!history.length && <p className="text-sm text-gray-500">Your conversations will appear here.</p>}
        <div className="max-h-64 space-y-1 overflow-auto lg:max-h-[60vh]">{history.map((item) => <div key={item.id} className={`flex items-center gap-1 rounded-lg ${activeId.current === item.id ? 'bg-indigo-50' : 'hover:bg-gray-50'}`}>
          <button onClick={() => open(item.id)} disabled={submitting} className="min-w-0 flex-1 truncate p-2 text-left text-sm text-gray-700">{item.title}</button>
          <button onClick={() => remove(item.id)} disabled={submitting} aria-label={`Delete ${item.title}`} className="px-2 text-gray-400 hover:text-red-600">×</button>
        </div>)}</div>
        {(page > 1 || hasMore) && <div className="flex justify-between text-xs"><button disabled={page === 1} onClick={() => setPage((value) => value - 1)}>Previous</button><button disabled={!hasMore} onClick={() => setPage((value) => value + 1)}>Next</button></div>}
      </aside>
      <section aria-label="Card conversation" className="flex min-w-0 flex-col rounded-xl border bg-white shadow-sm">
        <div className="grid gap-3 border-b p-4 sm:grid-cols-2">
          <label className="text-xs text-gray-500">Card scope<select aria-label="Card scope" disabled={Boolean(conversation) || loading || submitting} value={cardId} onChange={(event) => setCardId(event.target.value)} className="mt-1 w-full rounded border bg-white px-2 py-2 text-sm text-gray-800"><option value="">All accessible cards</option>{cards.map((card) => <option key={card.id} value={card.id}>{card.bank_name} {card.card_name} · {card.last_four_digits}</option>)}</select></label>
          <label className="text-xs text-gray-500">Statement PDF<select aria-label="Statement PDF" disabled={Boolean(conversation) || loading || submitting} value={statementId} onChange={(event) => setStatementId(event.target.value)} className="mt-1 w-full rounded border bg-white px-2 py-2 text-sm text-gray-800"><option value="">No PDF selected</option>{statements.map((statement) => <option key={statement.id} value={statement.id}>{statement.card_name} · {statement.original_filename}</option>)}{conversation?.statement_id && !statements.some((statement) => statement.id === conversation.statement_id) && <option value={conversation.statement_id}>Selected saved statement</option>}</select></label>
        </div>
        <div ref={scroll} onScroll={() => { const node = scroll.current; nearBottom.current = node.scrollHeight - node.scrollTop - node.clientHeight < 80 }} className="h-[55vh] min-h-64 space-y-4 overflow-y-auto p-4" aria-live="polite">
          {loading && <p className="text-sm text-gray-500">Loading conversation…</p>}
          {!loading && !conversation?.messages?.length && !restricted && <div className="space-y-4 py-8"><p className="text-sm text-gray-600">Ask a question to start. Answers use your saved data and cite issuer research when needed.</p><div className="grid gap-2 sm:grid-cols-2">{prompts.map((prompt) => <button key={prompt} disabled={submitting} onClick={() => setDraft(prompt)} className="rounded-lg border bg-gray-50 p-3 text-left text-sm text-gray-700 hover:border-indigo-300">{prompt}</button>)}</div></div>}
          {conversation?.messages?.map((message) => <article key={message.id} className={`max-w-full rounded-xl p-3 ${message.role === 'user' ? 'ml-8 bg-indigo-50' : 'mr-4 border bg-white'}`}>
            <p className="mb-1 text-xs font-medium text-gray-500">{message.role === 'user' ? 'You' : 'Card Chat'}</p>
            <p className="whitespace-pre-wrap break-words text-sm leading-6 text-gray-800">{message.status === 'processing' ? 'Checking your cards and sources…' : message.content}</p>
            {message.sources?.length > 0 && <div className="mt-3 flex flex-wrap gap-2" aria-label="Answer sources">{message.sources.map((source) => <SourceLink key={source.id} source={source} />)}</div>}
            {message.status === 'failed' && <button onClick={() => submit(null, conversation.messages.find((row) => row.role === 'user' && row.client_id === message.client_id))} disabled={submitting || !online} className="mt-2 text-sm text-indigo-600">Retry answer</button>}
          </article>)}
        </div>
        <form onSubmit={submit} className="space-y-2 border-t p-4">
          {!online && <p role="status" className="text-sm text-amber-700">Card Chat needs an online connection. Your draft stays here.</p>}
          {error && <p role="alert" className="text-sm text-red-700">{error}{pending && <button type="button" onClick={() => { setError(null); setPollVersion((value) => value + 1) }} className="ml-2 underline">Check again</button>}</p>}
          <label htmlFor="chat-question" className="sr-only">Your question</label>
          <textarea id="chat-question" value={draft} onChange={(event) => setDraft(event.target.value)} maxLength={4000} rows={3} disabled={submitting || Boolean(pending) || restricted} placeholder="Ask about your cards…" className="w-full resize-y rounded-lg border p-3 text-sm focus:outline-indigo-500 disabled:bg-gray-50" />
          <div className="flex items-center justify-between gap-3"><p className="text-xs text-gray-500">Read-only answers. Selected PDFs and relevant data are processed by AI.</p><button disabled={!draft.trim() || submitting || Boolean(pending) || !online || restricted || loading} className="shrink-0 whitespace-nowrap rounded-lg bg-indigo-600 px-4 py-2 text-sm text-white disabled:opacity-50">{submitting ? 'Sending…' : 'Send'}</button></div>
        </form>
      </section>
    </div>
  </div>
}
