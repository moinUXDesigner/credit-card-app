import client from './client'

export const listCards = () => client.get('/cards').then((r) => r.data)
export const getCard = (id) => client.get(`/cards/${id}`).then((r) => r.data)
export const createCard = (payload) => client.post('/cards', payload).then((r) => r.data)
export const updateCard = (id, payload) => client.put(`/cards/${id}`, payload).then((r) => r.data)
export const deleteCard = (id) => client.delete(`/cards/${id}`).then((r) => r.data)

export const importCards = (file) => {
  const formData = new FormData()
  formData.append('file', file)
  return client.post('/cards/import', formData).then((r) => r.data)
}
