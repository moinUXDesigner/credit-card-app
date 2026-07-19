import client from './client'

export const getSpendByCategory = (months) =>
  client.get('/reports/spend-by-category', { params: { months } }).then((r) => r.data)
