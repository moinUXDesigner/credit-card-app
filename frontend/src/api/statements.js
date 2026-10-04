import { waitForAi } from './aiPolling'
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

const fetchStatementBlobUrl = async (id) => {
  const response = await client.get(`/statements/${id}/download`, { responseType: 'blob' })
  return window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
}

export const getStatementViewUrl = (id) => fetchStatementBlobUrl(id)

export const downloadStatement = async (id, filename) => {
  const url = await fetchStatementBlobUrl(id)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}

export const previewStatement = (file, cardId, statementId) => {
  const form = new FormData()
  if (file) form.append('file', file)
  if (cardId) form.append('card_id', cardId)
  if (statementId) form.append('statement_id', statementId)
  return client.post('/statement-previews', form, { timeout: 30000 }).then((r) => waitForAi(r.data, () => getStatementPreview(r.data.preview_id)))
}
export const getStatementPreview = (id) => client.get(`/statement-previews/${id}`).then((r) => r.data)
export const confirmStatement = (cardId, payload, file) => client.post(`/cards/${cardId}/statements`, payload, { retainedStatementFile: file }).then((r) => r.data)

export const updateTransactionCategory = (statementId, transactionId, category) => client.patch(`/statements/${statementId}/transactions/${transactionId}/category`, { category }).then((r) => r.data)
