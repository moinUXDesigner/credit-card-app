import client from './client'

export const getMonthlyReport = (year, month) =>
  client.get('/reports/monthly', { params: { year, month } }).then((r) => r.data)

export const logMonthlySpend = (cardId, payload) =>
  client.post(`/cards/${cardId}/spend-entries`, payload).then((r) => r.data)
