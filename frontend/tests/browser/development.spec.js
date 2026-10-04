import { test, expect } from '@playwright/test'
const api = 'http://localhost:8080/api'
const cardPayload = {
  card_name: 'Browser test card',
  bank_name: 'Test Bank',
  last_four_digits: '1234',
  network: 'visa',
  total_limit: 100000,
  current_outstanding: 1000,
  statement_day: 1,
  due_day: 20,
  annual_fee_amount: 0,
  annual_fee_month: 1,
  waiver_spend_required: 10000,
  waiver_spend_completed: 0,
  card_year_start_month: 1,
  reward_point_balance: 0,
  reward_point_value_estimate: 0,
  best_categories: [],
  lounge_access: false,
  is_active: true,
}
async function account(page, request) {
  const email = `browser-${Date.now()}-${Math.random().toString(36).slice(2)}@example.test`,
    password = 'BrowserTest@123'
  const response = await request.post(`${api}/auth/register`, {
    data: { name: 'Browser Test', email, password, password_confirmation: password },
  })
  expect(response.ok()).toBeTruthy()
  const auth = await response.json()
  await page.addInitScript(
    (auth) =>
      localStorage.setItem(
        'ccapp-auth',
        JSON.stringify({ state: { token: auth.access_token, user: auth.user }, version: 0 }),
      ),
    auth,
  )
  await page.goto('/sync')
  await page.getByRole('button', { name: 'Enable offline storage' }).click()
  await expect(page.getByRole('button', { name: 'Sync now', exact: true })).toBeVisible()
  await page.evaluate(async () => {
    window.syncEngine = await import('/src/sync/engine.js')
    window.syncStorage = await import('/src/sync/storage.js')
  })
  return auth
}
async function queue(page, method, path, payload) {
  return page.evaluate(
    async ({ method, path, payload }) => window.syncEngine.enqueue(method, path, payload),
    { method, path, payload },
  )
}
async function syncDone(page) {
  await page.evaluate(() => window.syncEngine.flush())
  await expect
    .poll(() =>
      page.evaluate(async () => {
        const id = JSON.parse(localStorage.getItem('ccapp-auth')).state.user.id
        return (await window.syncStorage.readAccount(id)).queue.map((x) => [x.state, x.error])
      }),
    )
    .toEqual([])
}
test('development ledger is public; production ledger requires login', async ({ page }) => {
  await page.goto('/development-ledger')
  await expect(page.getByRole('heading', { name: 'Development Ledger', exact: true })).toBeVisible()
  await page.screenshot({path:'/tmp/cc-development-ledger.png',fullPage:false})
  await expect(page.getByRole('button', { name: /Scoped/ })).toContainText('24')
  await expect(page.getByRole('button', { name: /Built/ })).toContainText('23')
  await expect(page.getByRole('button', { name: /Pending/ })).toContainText('1')
  await page.getByRole('searchbox').fill('Android')
  await expect(page.locator('tbody tr')).toHaveCount(1)
  await page.goto('http://localhost:4174/development-ledger')
  await expect(page).toHaveURL(/\/login$/)
})
test('offline card, benefit and spend survive reload and replay in dependency order', async ({
  page,
  context,
  request,
}) => {
  const auth = await account(page, request)
  await context.setOffline(true)
  const card = await queue(page, 'POST', '/cards', cardPayload)
  await queue(page, 'POST', `/cards/${card.id}/benefits`, {
    type: 'lounge',
    title: 'Offline benefit',
    frequency: 'yearly',
    total_allowed: 2,
    used_count: 0,
  })
  await queue(page, 'POST', `/cards/${card.id}/spend-entries`, {
    year: 2026,
    month: 10,
    category: 'other',
    amount_spent: 400,
  })
  await page.goto('/cards')
  await expect(page.getByRole('link', { name: 'View Test Bank Browser test card', exact: true })).toBeVisible()
  await page.reload()
  await expect(page.getByRole('link', { name: 'View Test Bank Browser test card', exact: true })).toBeVisible()
  await expect(page.getByText('Pending sync')).toBeVisible()
  await context.setOffline(false)
  await page.evaluate(async () => {
    window.syncEngine = await import('/src/sync/engine.js')
    window.syncStorage = await import('/src/sync/storage.js')
  })
  await syncDone(page)
  const response = await request.get(`${api}/cards`, {
    headers: { Authorization: `Bearer ${auth.access_token}` },
  })
  const cards = await response.json()
  expect(cards).toHaveLength(1)
  const id = cards[0].id
  const benefits = await request.get(`${api}/cards/${id}/benefits`, {
    headers: { Authorization: `Bearer ${auth.access_token}` },
  })
  expect((await benefits.json())[0].title).toBe('Offline benefit')
  await request.delete(`${api}/cards/${id}`, {
    headers: { Authorization: `Bearer ${auth.access_token}` },
  })
})
test('cross-session edit conflicts preserve local work and allow explicit reapply', async ({
  page,
  context,
  request,
}) => {
  const auth = await account(page, request)
  const created = await request.post(`${api}/cards`, {
    data: cardPayload,
    headers: { Authorization: `Bearer ${auth.access_token}` },
  })
  const card = await created.json()
  await page.evaluate(() => window.syncEngine.flush())
  await context.setOffline(true)
  await queue(page, 'PUT', `/cards/${card.id}`, { card_name: 'Local edit' })
  await request.put(`${api}/cards/${card.id}`, {
    data: { card_name: 'Other session edit' },
    headers: { Authorization: `Bearer ${auth.access_token}` },
  })
  await context.setOffline(false)
  await page.evaluate(() => window.syncEngine.flush())
  await page.goto('/sync')
  await expect(page.getByText('Server record', { exact: true })).toBeVisible()
  await expect(page.getByText('Local edit', { exact: false })).toBeVisible()
  await page.getByRole('button', { name: 'Apply my version to latest record' }).click()
  await expect(page.getByText('No unsynchronized changes.')).toBeVisible()
  const response = await request.get(`${api}/cards/${card.id}`, {
    headers: { Authorization: `Bearer ${auth.access_token}` },
  })
  expect((await response.json()).card_name).toBe('Local edit')
  await request.delete(`${api}/cards/${card.id}`, {
    headers: { Authorization: `Bearer ${auth.access_token}` },
  })
})
test('deleting an unsent card cancels its children and pending files', async ({
  page,
  context,
  request,
}) => {
  await account(page, request)
  await context.setOffline(true)
  const card = await queue(page, 'POST', '/cards', cardPayload)
  await queue(page, 'POST', `/cards/${card.id}/benefits`, {
    type: 'lounge',
    title: 'Cancel child',
    frequency: 'yearly',
    total_allowed: 2,
  })
  await queue(page, 'DELETE', `/cards/${card.id}`, {})
  await page.goto('/sync')
  await expect(page.getByText('No unsynchronized changes.')).toBeVisible()
})
test('queued uploads and imports process once and pending quota is enforced', async ({
  page,
  context,
  request,
}) => {
  const auth = await account(page, request)
  const response = await request.post(`${api}/cards`, {
    data: cardPayload,
    headers: { Authorization: `Bearer ${auth.access_token}` },
  })
  const card = await response.json()
  await page.evaluate(() => window.syncEngine.flush())
  await context.setOffline(true)
  await page.evaluate(async (id) => {
    const form = new FormData()
    form.append('file', new File(['%PDF-1.4\n%%EOF'], 'queued.pdf', { type: 'application/pdf' }))
    form.append('billing_month', '10')
    form.append('billing_year', '2026')
    await window.syncEngine.enqueue('POST', `/cards/${id}/statements`, form)
  }, card.id)
  await page.evaluate(async (payload) => {
    const form = new FormData()
    form.append(
      'file',
      new File([JSON.stringify([{ ...payload, card_name: 'Queued import' }])], 'cards.json', {
        type: 'application/json',
      }),
    )
    await window.syncEngine.enqueue('POST', '/cards/import', form)
  }, cardPayload)
  const quota = await page.evaluate(async () => {
    const id = JSON.parse(localStorage.getItem('ccapp-auth')).state.user.id
    await window.syncStorage.changeAccount(id, (a) => {
      a.queue.push({
        operation_id: 'quota-fixture',
        state: 'blocked',
        file: new Blob([new Uint8Array(99 * 1024 * 1024)]),
        path: '/cards/import',
        payload: {},
      })
    })
    const f = new FormData()
    f.append('file', new File([new Uint8Array(2 * 1024 * 1024)], 'too-much.json'))
    try {
      await window.syncEngine.enqueue('POST', '/cards/import', f)
      return false
    } catch (e) {
      return e.message.includes('100 MB')
    } finally {
      await window.syncStorage.changeAccount(id, (a) => {
        a.queue = a.queue.filter((x) => x.operation_id !== 'quota-fixture')
      })
    }
  })
  expect(quota).toBeTruthy()
  await context.setOffline(false)
  await syncDone(page)
  const statements = await request.get(`${api}/cards/${card.id}/statements`, {
    headers: { Authorization: `Bearer ${auth.access_token}` },
  })
  expect(await statements.json()).toHaveLength(1)
  const cards = await request.get(`${api}/cards`, {
    headers: { Authorization: `Bearer ${auth.access_token}` },
  })
  for (const c of await cards.json())
    await request.delete(`${api}/cards/${c.id}`, {
      headers: { Authorization: `Bearer ${auth.access_token}` },
    })
})
test('revoked shared data is purged and queued writes are blocked on reconnection', async ({
  page,
  context,
  request,
}) => {
  const member = await account(page, request)
  const ownerResponse = await request.post(`${api}/auth/register`, {
    data: {
      name: 'Browser Owner',
      email: `browser-owner-${Date.now()}@example.test`,
      password: 'BrowserTest@123',
      password_confirmation: 'BrowserTest@123',
    },
  })
  const owner = await ownerResponse.json()
  const headers = { Authorization: `Bearer ${owner.access_token}` }
  const card = await (await request.post(`${api}/cards`, { data: cardPayload, headers })).json()
  await request.post(`${api}/cards/${card.id}/sharing`, {
    data: { email: member.user.email, permission: 'editor' },
    headers,
  })
  const invitations = await (
    await request.get(`${api}/invitations`, {
      headers: { Authorization: `Bearer ${member.access_token}` },
    })
  ).json()
  const invitation = invitations[0]
  await request.post(`${api}/invitations/${invitation.id}/accept`, {
    headers: { Authorization: `Bearer ${member.access_token}` },
  })
  await page.evaluate(() => window.syncEngine.flush())
  await context.setOffline(true)
  await queue(page, 'PUT', `/cards/${card.id}`, { card_name: 'Revoked local edit' })
  await request.delete(`${api}/cards/${card.id}/sharing/${invitation.id}`, { headers })
  await context.setOffline(false)
  await page.evaluate(() => window.syncEngine.flush())
  const data = await page.evaluate(async () => {
    const id = JSON.parse(localStorage.getItem('ccapp-auth')).state.user.id
    return window.syncStorage.readAccount(id)
  })
  expect(data.caches['/cards']).toEqual([])
  expect(data.queue[0].state).toBe('blocked')
  expect(Object.keys(data.caches)).not.toContain(`/cards/${card.id}`)
  await request.delete(`${api}/cards/${card.id}`, { headers })
})
test('explicit logout warns about pending work and clears account storage', async ({
  page,
  context,
  request,
}) => {
  const auth = await account(page, request)
  await context.setOffline(true)
  await queue(page, 'POST', '/cards', cardPayload)
  let dialogs = 0
  page.on('dialog', async (dialog) => {
    dialogs++
    if (dialogs === 1) await dialog.dismiss()
    else await dialog.accept()
  })
  await page.getByRole('button', { name: 'Logout', exact: true }).click()
  await expect.poll(() => dialogs).toBe(1)
  await expect(page).toHaveURL(/\/sync$/)
  await page.getByRole('button', { name: 'Logout', exact: true }).click()
  await expect(page).toHaveURL(/\/login$/)
  const accountState = await page.evaluate(async (id) => {
    const storage = await import('/src/sync/storage.js')
    return storage.readAccount(id)
  }, auth.user.id)
  expect(accountState.enabled).toBeFalsy()
  expect(accountState.queue).toEqual([])
  expect(accountState.caches).toEqual({})
})
test('expired sessions pause replay without losing queued data', async ({
  page,
  context,
  request,
}) => {
  await account(page, request)
  await context.setOffline(true)
  await queue(page, 'POST', '/cards', cardPayload)
  await page.evaluate(async () => {
    const { useAuthStore } = await import('/src/store/authStore.js')
    useAuthStore.getState().setAuth('expired-token', useAuthStore.getState().user)
  })
  await context.setOffline(false)
  await page.evaluate(() => window.syncEngine.flush())
  const state = await page.evaluate(async () =>
    window.syncStorage.readAccount(JSON.parse(localStorage.getItem('ccapp-auth')).state.user.id),
  )
  expect(state.queue).toHaveLength(1)
  expect(state.paused).toContain('Session expired')
})
test('production UI creates a card offline and reloads before syncing', async ({
  page,
  context,
  request,
}) => {
  const email = `browser-prod-${Date.now()}@example.test`,
    password = 'BrowserTest@123'
  const auth = await (
    await request.post(`${api}/auth/register`, {
      data: { name: 'Browser Production', email, password, password_confirmation: password },
    })
  ).json()
  await page.addInitScript(
    (auth) =>
      localStorage.setItem(
        'ccapp-auth',
        JSON.stringify({ state: { token: auth.access_token, user: auth.user }, version: 0 }),
      ),
    auth,
  )
  await page.goto('http://localhost:4174/sync')
  await page.getByRole('button', { name: 'Enable offline storage' }).click()
  await expect(page.getByRole('button', { name: 'Sync now', exact: true })).toBeVisible()
  await context.setOffline(true)
  await page.goto('http://localhost:4174/cards/new')
  await page.locator('input[name="card_name"]').fill('Production offline card')
  await page.locator('input[name="bank_name"]').fill('Test Bank')
  await page.locator('input[name="last_four_digits"]').fill('5678')
  await page.locator('input[name="total_limit"]').fill('100000')
  await page.getByRole('button', { name: 'Add card', exact: true }).click()
  await expect(page).toHaveURL(/\/cards$/)
  await page.reload()
  await expect(page.getByRole('link', { name: 'View Test Bank Production offline card', exact: true })).toBeVisible()
  await expect(page.getByText('Pending sync')).toBeVisible()
  await context.setOffline(false)
  await page.getByRole('link', { name: 'Sync Center', exact: true }).first().click()
  await page.getByRole('button', { name: 'Sync now', exact: true }).click()
  await expect(page.getByText('No unsynchronized changes.')).toBeVisible()
  const cards = await (
    await request.get(`${api}/cards`, { headers: { Authorization: `Bearer ${auth.access_token}` } })
  ).json()
  expect(cards).toHaveLength(1)
  await request.delete(`${api}/cards/${cards[0].id}`, {
    headers: { Authorization: `Bearer ${auth.access_token}` },
  })
})
test('message preview UI confirms a purchase without applying an unchecked summary', async ({page,request})=>{
 const auth=await account(page,request),headers={Authorization:`Bearer ${auth.access_token}`}
 const card=await (await request.post(`${api}/cards`,{data:cardPayload,headers})).json()
 await page.goto('/import-messages');await page.getByLabel('Destination card').selectOption(String(card.id));await page.getByLabel('Message text').fill('INR 500 spent at Grocery Store on 2026-10-01\nTotal due: INR 250');await page.getByRole('button',{name:'Preview messages'}).click();await expect(page.getByRole('heading',{name:'Review extracted data'})).toBeVisible();await page.getByRole('button',{name:'Confirm import'}).click();await expect(page.getByRole('status').filter({hasText:'Imported 1 transactions.'})).toBeVisible()
 const updated=await (await request.get(`${api}/cards/${card.id}`,{headers})).json();expect(updated.current_outstanding).toBe(1000);await request.delete(`${api}/cards/${card.id}`,{headers})
})
test('admin UI exposes account operations and health',async({page,request})=>{
 const response=await request.post(`${api}/auth/login`,{data:{email:'admin@example.com',password:'AdminUser@123'}});expect(response.ok()).toBeTruthy();const auth=await response.json();expect(auth.user.role).toBe('admin');await page.addInitScript(auth=>localStorage.setItem('ccapp-auth',JSON.stringify({state:{token:auth.access_token,user:auth.user},version:0})),auth);await page.goto('/admin');await expect(page.getByRole('heading',{name:'Admin operations'})).toBeVisible();await expect(page.getByRole('heading',{name:'System health'})).toBeVisible();await page.getByRole('textbox',{name:'Search users'}).fill('demo@example.com');await page.getByRole('button',{name:'Search',exact:true}).click();await expect(page.locator('tbody tr')).toHaveCount(1);await expect(page.locator('tbody')).toContainText('demo@example.com')
})
test('switching accounts warns and clears only the old account partition',async({page,context,request})=>{
 const old=await account(page,request);await context.setOffline(true);await queue(page,'POST','/cards',cardPayload);await page.evaluate(async()=>{const id=JSON.parse(localStorage.getItem('ccapp-auth')).state.user.id;await window.syncStorage.changeAccount(id,a=>{a.queue[0].state='failed'})})
 const email=`browser-switch-${Date.now()}@example.test`,password='BrowserTest@123';await request.post(`${api}/auth/register`,{data:{name:'Browser Switch',email,password,password_confirmation:password}});await context.setOffline(false);await page.goto('/login');await page.locator('input[type="email"]').fill(email);await page.locator('input[type="password"]').fill(password)
 let dialogs=0;page.on('dialog',async dialog=>{dialogs++;if(dialogs===1)await dialog.dismiss();else await dialog.accept()});await page.getByRole('button',{name:'Log in',exact:true}).click();await expect(page.getByText('Account switch cancelled.')).toBeVisible();await page.getByRole('button',{name:'Log in',exact:true}).click();await expect(page).toHaveURL('http://localhost:5178/')
 const state=await page.evaluate(async oldId=>{const storage=await import('/src/sync/storage.js');const auth=JSON.parse(localStorage.getItem('ccapp-auth')).state;return {email:auth.user.email,old:await storage.readAccount(oldId),current:await storage.readAccount(auth.user.id)}},old.user.id);expect(state.email).toBe(email);expect(state.old.queue).toEqual([]);expect(state.current.enabled).toBe(false)
})
