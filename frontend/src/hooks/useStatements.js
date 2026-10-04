import { useCallback, useEffect, useState, useRef } from 'react'
import { listStatements } from '../api/statements'

export function useStatements(cardId) {
  const [statements, setStatements] = useState([])
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(true)

  const loaded = useRef(false)
  const requestId = useRef(0)
  const invalidatePending = useCallback(() => { requestId.current++ }, [])

  const refresh = useCallback(async () => {
    const current = ++requestId.current
    if (!cardId) {
      setStatements([])
      setLoading(false)
      return
    }
    if (!loaded.current) setLoading(true)
    setError(null)
    try {
      const data = await listStatements(cardId)
      if (current !== requestId.current) return
      loaded.current = true
      setStatements(data)
    } catch (err) {
      if (current === requestId.current) setError(err.response?.data?.message ?? 'Could not load statements.')
    } finally {
      if (current === requestId.current) setLoading(false)
    }
  }, [cardId])

  useEffect(() => {
    loaded.current = false
    setStatements([])
    refresh()
    window.addEventListener('sync-data', refresh)
    return () => { invalidatePending(); window.removeEventListener('sync-data', refresh) }
  }, [refresh, invalidatePending])

  return { statements, loading, error, refresh }
}
