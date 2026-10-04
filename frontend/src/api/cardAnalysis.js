import { waitForAi } from './aiPolling'
import { getStatementPreview } from './statements'
import client from './client'

export const analyzeCard = (file) => {
  const formData = new FormData()
  formData.append('file', file)
  return client.post('/cards/analyze', formData, { timeout: 150000 }).then(async (r) => {
    if (!r.data.preview_id) return r.data
    const preview = await waitForAi(r.data, () => getStatementPreview(r.data.preview_id))
    return { ...preview, preview }
  })
}
