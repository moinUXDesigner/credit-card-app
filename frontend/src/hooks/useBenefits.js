import { useCallback, useEffect, useState } from 'react'
import { listBenefits } from '../api/benefits'

export function useBenefits(cardId) {
  const [benefits, setBenefits] = useState([])
  const [loading, setLoading] = useState(false)

  const refresh = useCallback(async () => {
    if (!cardId) {
      setBenefits([])
      return
    }
    setLoading(true)
    try {
      const data = await listBenefits(cardId)
      setBenefits(data)
    } finally {
      setLoading(false)
    }
  }, [cardId])

  useEffect(() => {
    refresh()
    window.addEventListener('sync-data', refresh)
    return () => window.removeEventListener('sync-data', refresh)
  }, [refresh])

  return { benefits, loading, refresh }
}
