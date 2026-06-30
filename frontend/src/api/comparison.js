import client from './client'

export const getComparison = (cardIds) =>
  client.get('/comparison', { params: { card_ids: cardIds } }).then((r) => r.data)
