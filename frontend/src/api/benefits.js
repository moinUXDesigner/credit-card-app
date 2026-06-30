import client from './client'

export const listBenefits = (cardId) => client.get(`/cards/${cardId}/benefits`).then((r) => r.data)
export const createBenefit = (cardId, payload) =>
  client.post(`/cards/${cardId}/benefits`, payload).then((r) => r.data)
export const updateBenefit = (id, payload) => client.put(`/benefits/${id}`, payload).then((r) => r.data)
export const deleteBenefit = (id) => client.delete(`/benefits/${id}`).then((r) => r.data)
export const markBenefitUsed = (id) => client.post(`/benefits/${id}/mark-used`).then((r) => r.data)
