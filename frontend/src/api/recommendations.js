import client from './client'

export const getRecommendation = (category) =>
  client.get('/recommendation', { params: category ? { category } : {} }).then((r) => r.data)

export const getMonthlyPlan = (categories, topN) =>
  client
    .get('/recommendation/monthly-plan', {
      params: { categories, top_n: topN },
      paramsSerializer: { indexes: false },
    })
    .then((r) => r.data)
