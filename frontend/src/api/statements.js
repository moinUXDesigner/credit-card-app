import client from './client'

export const listStatements = (cardId) => client.get(`/cards/${cardId}/statements`).then((r) => r.data)

export const uploadStatement = (cardId, file, billingMonth, billingYear) => {
  const formData = new FormData()
  formData.append('file', file)
  formData.append('billing_month', billingMonth)
  formData.append('billing_year', billingYear)
  return client
    .post(`/cards/${cardId}/statements`, formData, { headers: { 'Content-Type': 'multipart/form-data' } })
    .then((r) => r.data)
}

export const deleteStatement = (id) => client.delete(`/statements/${id}`).then((r) => r.data)

export const downloadStatement = async (id, filename) => {
  const response = await client.get(`/statements/${id}/download`, { responseType: 'blob' })
  const url = window.URL.createObjectURL(new Blob([response.data]))
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}
