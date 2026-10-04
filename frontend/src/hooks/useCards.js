import { useCallback, useEffect, useState, useRef } from 'react'
import { listCards } from '../api/cards'

export function useCards() {
  const [cards, setCards] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const loaded = useRef(false)
  const requestId = useRef(0)
  const invalidatePending = useCallback(() => { requestId.current++ }, [])

  const refresh = useCallback(async () => {
    const current = ++requestId.current
    if (!loaded.current) setLoading(true)
    setError(null)
    try {
      const data = await listCards()
      if (current !== requestId.current) return
      loaded.current = true
      setCards(data)
    } catch (err) {
      if (current === requestId.current) setError(err)
    } finally {
      if (current === requestId.current) setLoading(false)
    }
  }, [])

  useEffect(() => {
    refresh()
    window.addEventListener('sync-data', refresh)
    return () => { invalidatePending(); window.removeEventListener('sync-data', refresh) }
  }, [refresh, invalidatePending])

  return { cards, loading, error, refresh }
}
