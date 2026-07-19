import client from './client'

export const analyzeCard = (file) => {
  const formData = new FormData()
  formData.append('file', file)
  return client.post('/cards/analyze', formData, { timeout: 90000 }).then((r) => r.data)
}
