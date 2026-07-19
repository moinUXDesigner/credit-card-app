import { useCallback, useEffect, useState } from 'react'
import { listStatements } from '../api/statements'

export function useStatements(cardId) {
  const [statements, setStatements] = useState([])
  const [loading, setLoading] = useState(false)

  const refresh = useCallback(async () => {
    if (!cardId) {
      setStatements([])
      return
    }
    setLoading(true)
    try {
      const data = await listStatements(cardId)
      setStatements(data)
    } finally {
      setLoading(false)
    }
  }, [cardId])

  useEffect(() => {
    refresh()
  }, [refresh])

  return { statements, loading, refresh }
}
