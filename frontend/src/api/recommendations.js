import client from './client'

export const getRecommendation = (category) =>
  client.get('/recommendation', { params: category ? { category } : {} }).then((r) => r.data)
