import client from './client'
export const listConversations = (page = 1) => client.get('/chat/conversations', { params: { page } }).then((r) => r.data)
export const createConversation = (context) => client.post('/chat/conversations', context).then((r) => r.data)
export const getConversation = (id) => client.get(`/chat/conversations/${id}`).then((r) => r.data)
export const deleteConversation = (id) => client.delete(`/chat/conversations/${id}`)
export const sendChatMessage = (id, payload) => client.post(`/chat/conversations/${id}/messages`, payload).then((r) => r.data)
export const getAiRequest = (id) => client.get(`/ai/requests/${id}`).then((r) => r.data)
