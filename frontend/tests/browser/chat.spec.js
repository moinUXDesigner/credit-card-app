import { test, expect } from '@playwright/test'

test.use({ serviceWorkers: 'block' })
const card = { id: 10, card_name: 'Octane', bank_name: 'SBI Card', last_four_digits: '7245', permission: 'owner' }
async function setup(page, { fail = false, answerFailure = false } = {}) {
  let conversation = null, polls = 0, sends = [], created = [], finished = false
  await page.addInitScript(() => localStorage.setItem('ccapp-auth', JSON.stringify({ state: { token: 'chat-test', user: { id: 999, name: 'Test User', role: 'user' } }, version: 0 })))
  await page.route('**/api/**', async (route) => {
    const pathname = new URL(route.request().url()).pathname
    if (!pathname.startsWith('/api/')) return route.continue()
    const path = pathname.slice(4), method = route.request().method()
    let data = [], status = 200
    if (path === '/cards') data = [card]
    else if (path === '/cards/10/statements') data = [{ id: 11, original_filename: 'statement.pdf', billing_month: 10, billing_year: 2026 }]
    else if (path === '/chat/conversations' && method === 'POST') {
      created.push(route.request().postDataJSON())
      conversation = { id: 'conversation-1', title: 'What is my balance?', ...created.at(-1), messages: [] }
      data = conversation; status = 201
    } else if (path === '/chat/conversations') data = { data: conversation ? [conversation] : [], next_page_url: null }
    else if (path === '/chat/conversations/conversation-1' && method === 'DELETE') { conversation = null; status = 204; data = null }
    else if (path === '/chat/conversations/conversation-1') data = conversation
    else if (path === '/chat/conversations/conversation-1/messages') {
      sends.push(route.request().postDataJSON())
      if (fail) { data = { message: 'Card Chat is not configured yet.' }; status = 503 }
      else {
        const body = sends.at(-1)
        conversation.messages = [{ id: 'user-1', role: 'user', status: 'ready', content: body.content, client_id: body.client_id }, { id: 'assistant-1', role: 'assistant', status: 'processing', request_id: 'request-1', client_id: body.client_id }]
        finished = false
        data = { request_id: 'request-1', status: 'processing' }; status = 202
      }
    } else if (path === '/ai/requests/request-1') {
      polls++
      if (!finished) {
        conversation.messages[1] = { ...conversation.messages[1], status: answerFailure ? 'failed' : 'ready', content: answerFailure ? 'Could not answer. Retry.' : 'Your saved outstanding is ₹22,471.', sources: answerFailure ? [] : [{ id: 'card:10', type: 'card', title: 'SBI Card Octane', url: '/cards/10' }, { id: 'web:1', type: 'web', title: 'Official SBI benefits', url: 'https://www.sbicard.com/product', checked_at: '2026-10-04' }] }
        finished = true
      }
      data = { status: answerFailure ? 'failed' : 'ready' }
    }
    await route.fulfill({ status, json: data, headers: { 'access-control-allow-origin': '*' } })
  })
  return { sends, created, polls: () => polls }
}

test('Card Chat saves conversations, polls answers, renders sources and deletes history', async ({ page }) => {
  const state = await setup(page)
  await page.goto('/chat')
  await page.getByRole('combobox', { name: 'Card scope' }).selectOption('10')
  await page.getByRole('combobox', { name: 'Statement PDF' }).selectOption('11')
  await page.getByLabel('Your question').fill('What is my balance?')
  await page.getByRole('button', { name: 'Send', exact: true }).click()
  await expect(page.getByText('Your saved outstanding is ₹22,471.')).toBeVisible()
  await expect(page.getByRole('link', { name: 'Official SBI benefits' })).toHaveAttribute('href', 'https://www.sbicard.com/product')
  expect(state.created).toEqual([{ card_id: 10, statement_id: 11 }])
  expect(state.sends).toHaveLength(1)
  await expect(page.getByLabel('Your question')).toHaveValue('')
  await page.reload()
  await page.getByRole('button', { name: 'What is my balance?', exact: true }).click()
  await expect(page.getByText('Your saved outstanding is ₹22,471.')).toBeVisible()
  page.once('dialog', (dialog) => dialog.accept())
  await page.getByRole('button', { name: 'Delete What is my balance?' }).click()
  await expect(page.getByText('Your conversations will appear here.')).toBeVisible()
})

test('unconfigured chat keeps the draft and uses the same retry ID', async ({ page }) => {
  const state = await setup(page, { fail: true })
  await page.goto('/chat')
  await page.getByLabel('Your question').fill('Which card is best for fuel?')
  await page.getByRole('button', { name: 'Send', exact: true }).click()
  await expect(page.getByRole('alert')).toContainText('not configured')
  await expect(page.getByLabel('Your question')).toHaveValue('Which card is best for fuel?')
  await page.getByRole('button', { name: 'Send', exact: true }).click()
  await expect.poll(() => state.sends.length).toBe(2)
  expect(state.sends[0].client_id).toBe(state.sends[1].client_id)
  expect(state.created).toHaveLength(1)
})

test('failed answers retry without creating a new conversation and chat fits mobile', async ({ page }) => {
  const state = await setup(page, { answerFailure: true })
  await page.setViewportSize({ width: 375, height: 812 })
  await page.goto('/chat')
  await page.getByLabel('Your question').fill('Explain my rewards')
  await page.getByRole('button', { name: 'Send', exact: true }).click()
  await expect(page.getByRole('button', { name: 'Retry answer' })).toBeVisible()
  await page.getByRole('button', { name: 'Retry answer' }).click()
  await expect.poll(() => state.sends.length).toBe(2)
  expect(state.created).toHaveLength(1)
  expect(state.sends[0].client_id).toBe(state.sends[1].client_id)
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(375)
  await page.screenshot({ path: 'test-results/chat-mobile.png', fullPage: true })
})
