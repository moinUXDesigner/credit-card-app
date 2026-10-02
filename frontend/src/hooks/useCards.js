import { useCallback, useEffect, useState } from 'react'
import { listCards } from '../api/cards'

export function useCards() {
  const [cards, setCards] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const refresh = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const data = await listCards()
      setCards(data)
    } catch (err) {
      setError(err)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    refresh()
    window.addEventListener('sync-data', refresh)
    return () => window.removeEventListener('sync-data', refresh)
  }, [refresh])

  return { cards, loading, error, refresh }
}
