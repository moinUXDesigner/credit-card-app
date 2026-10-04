import { test, expect } from '@playwright/test'

test.use({ serviceWorkers: 'block' })
const original = { id: 10, revision: 1, permission: 'owner', card_name: 'Rewards', bank_name: 'Example Bank', last_four_digits: '0001', network: 'visa', total_limit: 100000, current_outstanding: 10000, statement_day: 12, due_day: 1, annual_fee_amount: 0, annual_fee_month: 1, card_year_start_month: 1, waiver_spend_required: 0, waiver_spend_completed: 0, waiver_remaining_spend: 0, waiver_months_left: 12, waiver_suggested_monthly_spend: 0, reward_point_balance: 0, reward_point_value_estimate: 0, reward_rate_general: null, cashback_cap_amount: null, forex_markup_percent: null, fuel_surcharge_waiver_percent: null, insurance_cover_amount: null, best_categories: [], utilization_percentage: 10, utilization_band: 'good', utilization_message: 'Healthy utilization' }
const draft = { preview_id: '9d539ef7-22ce-41c6-bf12-df89ead6a114', card_revision: 1, analyzed: true, original_filename: 'statement.pdf', summary: { last_four_digits: '0001', statement_date: '2026-07-12', due_date: '2026-08-01', total_due: 45000, minimum_due: 500, credit_limit: 100000, reward_point_balance: 850 }, rows: [{ transaction_date: '2026-07-01', description: 'Grocery shop', amount: 1000, direction: 'purchase', category: 'grocery', possible_duplicate: true }], apply_defaults: { current_outstanding: true, reward_point_balance: true, total_limit: false }, warnings: [] }
const file = { name: 'statement.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4 local test') }
async function setup(page, { viewer = false, failFirstImport = false, failedExtraction = false, paymentConflict = false } = {}) {
  let card = { ...original, permission: viewer ? 'viewer' : 'owner' }, imports = [], creates = [], statements = [], payments = [], gets = 0
  await page.addInitScript(() => localStorage.setItem('ccapp-auth', JSON.stringify({ state: { token: 'statement-test', user: { id: 999, name: 'Test User', role: 'user' } }, version: 0 })))
  await page.route('**/api/**', async (route) => {
    const pathname = new URL(route.request().url()).pathname
    if (!pathname.startsWith('/api/')) return route.continue()
    const path = pathname.replace(/^\/api/, '')
    const method = route.request().method()
    let data = [], status = 200
    if (path === '/cards/analyze') data = { document_recognized: true, card: { ...card, card_name: 'BPCL SBI Card OCTANE', reward_point_balance: 18791, current_outstanding: 45000 }, suggested_benefits: [] }
    else if (path === '/statement-previews') data = { ...draft, analyzed: !failedExtraction, summary: failedExtraction ? {} : draft.summary, rows: failFirstImport || failedExtraction ? [] : draft.rows }
    else if (path === '/cards' && method === 'POST') { creates.push(route.request().postDataJSON()); card = { ...card, ...creates.at(-1) }; data = card; status = 201 }
    else if (path === '/cards') data = [card]
    else if (path === '/cards/10/statements' && method === 'POST') {
      imports.push(route.request().headers()['content-type']?.includes('multipart/form-data') ? { staged: true } : route.request().postDataJSON())
      if (failFirstImport && imports.length === 1) { status = 503; data = { message: 'Temporary import failure' } }
      else if (route.request().headers()['content-type']?.includes('multipart/form-data')) { data = { id: 1, billing_month: 7, billing_year: 2026, original_filename: 'statement.pdf', total_due: null, analysis_status: 'pending', analysis_message: 'Awaiting review. No balances or spend have been changed.', transactions: [] }; statements = [data]; status = 201 }
      else { card = { ...card, current_outstanding: 45000, utilization_percentage: 45, utilization_band: 'caution' }; data = { id: 1, billing_month: 7, billing_year: 2026, total_due: 45000, original_filename: 'statement.pdf', analysis_status: 'completed', transactions: [] }; statements = [data]; status = 201 }
    } else if (path === '/cards/10/statements') data = statements
    else if (path === '/cards/10' && method === 'PUT') {
      const payload = route.request().postDataJSON()
      payments.push(payload)
      if (paymentConflict) { status = 409; data = { message: 'The record changed.' } }
      else { card = { ...card, ...payload, revision: card.revision + 1, utilization_percentage: payload.current_outstanding / 1000 }; data = card }
    }
    else if (path === '/cards/10') { gets++; data = card }
    await route.fulfill({ status, json: data, headers: { 'access-control-allow-origin': '*', 'access-control-allow-methods': 'GET, POST, PUT, OPTIONS', 'access-control-allow-headers': 'Authorization, Content-Type' } })
  })
  return { imports, creates, payments, getCardReads: () => gets }
}

test('card navigation, Edit, and statement deep links remain distinct', async ({ page }) => {
  await setup(page)
  await page.goto('/cards')
  const tile = page.getByRole('link', { name: 'View Example Bank Rewards' }).locator('../..')
  const bounds = await tile.boundingBox()
  // Click empty space at the bottom of the tile, outside the original heading link.
  await page.mouse.click(bounds.x + 20, bounds.y + bounds.height - 10)
  await expect(page).toHaveURL(/\/cards\/10$/)
  await expect(page.getByText('Total limit', { exact: true })).toBeVisible()
  await page.goto('/cards')
  await page.getByRole('link', { name: 'Edit', exact: true }).click()
  await expect(page).toHaveURL(/\/cards\/10\/edit$/)
  await page.goto('/cards')
  await page.getByRole('link', { name: 'Upload statement', exact: true }).click()
  await expect(page).toHaveURL(/tab=statements/)
  await page.reload()
  await expect(page.getByRole('button', { name: 'Upload statement PDF' })).toBeVisible()
})

test('statement review changes no data until confirmed and refreshes utilization', async ({ page }) => {
  const state = await setup(page)
  await page.goto('/cards/10?tab=statements')
  await page.locator('input[type=file]').setInputFiles(file)
  await expect(page.getByRole('heading', { name: 'Review statement' })).toBeVisible()
  expect(state.imports).toHaveLength(0)
  await expect(page.getByText(/Possible duplicate/)).toBeVisible()
  await page.getByLabel('Import action').selectOption('skip')
  await page.getByRole('button', { name: 'Confirm import' }).click()
  await expect(page.getByText('Statement saved.', { exact: true })).toBeVisible()
  expect(state.imports).toHaveLength(1)
  expect(state.imports[0].rows).toEqual([])
  expect(state.imports[0].apply_summary.total_limit).toBe(false)
  await page.getByRole('button', { name: 'Details', exact: true }).click()
  await expect(page.getByText('45.0%', { exact: true })).toBeVisible()
  expect(state.getCardReads()).toBeGreaterThan(1)
})

test('Add Card preserves manual edits and retries attaching the PDF without recreating the card', async ({ page }) => {
  const state = await setup(page, { failFirstImport: true })
  await page.goto('/cards/new')
  await page.locator('[name=card_name]').fill('My custom name')
  await page.locator('input[type=file]').first().setInputFiles(file)
  await expect(page.getByRole('heading', { name: 'Review statement' })).toBeVisible()
  await expect(page.locator('[name=card_name]')).toHaveValue('My custom name')
  await expect(page.locator('[name=last_four_digits]')).toHaveValue('0001')
  await expect(page.locator('[name=current_outstanding]')).toHaveValue('45000')
  await expect(page.getByText('45.0%', { exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Use reviewed statement' }).click()
  await page.getByRole('button', { name: 'Add card', exact: true }).click()
  await expect(page.getByRole('button', { name: 'Retry pending setup' })).toBeVisible()
  expect(state.creates).toHaveLength(1)
  await page.getByRole('button', { name: 'Retry pending setup' }).click()
  await expect(page).toHaveURL(/\/cards\/10\?tab=statements$/)
  expect(state.creates).toHaveLength(1)
  expect(state.imports).toHaveLength(2)
})

test('viewer sees saved statements without upload or Edit actions', async ({ page }) => {
  await setup(page, { viewer: true })
  await page.goto('/cards/10?tab=statements')
  await expect(page.getByText('No statements uploaded for this card yet.')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Upload statement PDF' })).toHaveCount(0)
  await expect(page.getByRole('link', { name: 'Edit card' })).toHaveCount(0)
})

test('unavailable card displays a retryable error instead of permanent loading', async ({ page }) => {
  await setup(page)
  await page.route('**/api/cards/10', (route) => route.fulfill({ status: 404, json: { message: 'Not found' }, headers: { 'access-control-allow-origin': '*' } }))
  await page.goto('/cards/10')
  await expect(page.getByText('This card is unavailable or you no longer have access.')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Retry' })).toBeVisible()
})

test('failed extraction supports PDF-only saving with explicit identity acknowledgement', async ({ page }) => {
  const state = await setup(page, { failedExtraction: true })
  await page.goto('/cards/10?tab=statements')
  await page.locator('input[type=file]').setInputFiles(file)
  const confirm = page.getByRole('button', { name: 'Confirm import' })
  await expect(confirm).toBeDisabled()
  await page.getByLabel('I confirm this PDF belongs to the destination card.').check()
  await confirm.click()
  await expect(page.getByText('Statement saved.', { exact: true })).toBeVisible()
  expect(state.imports[0].save_pdf_only).toBe(true)
  expect(state.imports[0].apply_summary).toEqual({})
  expect(state.imports[0].rows).toEqual([])
})

test('editing accepted review invalidates it and the review fits a mobile viewport', async ({ page }) => {
  await setup(page, { failFirstImport: true })
  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/cards/new')
  await page.locator('input[type=file]').first().setInputFiles(file)
  await page.getByRole('button', { name: 'Use reviewed statement' }).click()
  await expect(page.getByText('Statement reviewed.', { exact: false })).toBeVisible()
  await page.getByLabel('Total outstanding / amount due (₹)').fill('50000')
  await expect(page.getByText('Statement reviewed.', { exact: false })).toHaveCount(0)
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(391)
  await page.screenshot({ path: '/tmp/credit-card-statement-review.png', fullPage: true })
})

test('Add Card prefills the extracted product title and closing reward balance', async ({ page }) => {
  await setup(page)
  await page.goto('/cards/new')
  await page.locator('input[type=file]').first().setInputFiles(file)
  await expect(page.getByRole('heading', { name: 'Review statement' })).toBeVisible()
  await expect(page.locator('[name=card_name]')).toHaveValue('BPCL SBI Card OCTANE')
  await expect(page.locator('[name=reward_point_balance]')).toHaveValue('18791')
})


test('partial payment validates the amount and refreshes the outstanding and utilization', async ({ page }) => {
  const state = await setup(page)
  await page.goto('/cards')
  await page.getByRole('button', { name: 'Mark as paid' }).click()
  const dialog = page.getByRole('dialog', { name: 'Mark as paid' })
  await dialog.getByLabel('Part amount', { exact: true }).check()
  await dialog.getByLabel('Amount paid (₹)').fill('10001')
  await expect(dialog.getByRole('button', { name: 'Confirm payment' })).toBeDisabled()
  await dialog.getByLabel('Amount paid (₹)').fill('2500.50')
  await expect(dialog.getByText('₹7,499.5', { exact: true })).toBeVisible()
  await dialog.getByRole('button', { name: 'Confirm payment' }).click()
  await expect(dialog).not.toBeVisible()
  await expect(page.getByText(/Outstanding ₹7,499.5/)).toBeVisible()
  await expect(page.getByText('7.5%', { exact: true })).toBeVisible()
  expect(state.payments).toEqual([{ current_outstanding: 7499.5, revision: 1 }])
  await expect(page.getByRole('region', { name: 'Recent spends' })).toBeVisible()
  await expect(page.getByLabel('Recent spends total unavailable')).toHaveText('—')
})

test('full payment clears the balance and cancel makes no update', async ({ page }) => {
  const state = await setup(page)
  await page.goto('/cards/10')
  await page.getByRole('button', { name: 'Mark as paid' }).click()
  const dialog = page.getByRole('dialog', { name: 'Mark as paid' })
  await dialog.getByRole('button', { name: 'Cancel' }).click()
  expect(state.payments).toHaveLength(0)
  await page.getByRole('button', { name: 'Mark as paid' }).click()
  await dialog.getByRole('button', { name: 'Confirm payment' }).click()
  await expect(page.getByRole('button', { name: 'Mark as paid' })).toBeDisabled()
  expect(state.payments).toEqual([{ current_outstanding: 0, revision: 1 }])
  await expect(page.getByText('0.0%', { exact: true })).toBeVisible()
})

test('a payment conflict preserves the balance and keeps the error in the dialog', async ({ page }) => {
  await setup(page, { paymentConflict: true })
  await page.goto('/cards')
  await page.getByRole('button', { name: 'Mark as paid' }).click()
  const dialog = page.getByRole('dialog', { name: 'Mark as paid' })
  await dialog.getByRole('button', { name: 'Confirm payment' }).click()
  await expect(dialog.getByRole('alert')).toContainText('The balance changed')
  await expect(page.getByText(/Outstanding ₹10,000/)).toBeVisible()
})

test('viewers have no payment action', async ({ page }) => {
  await setup(page, { viewer: true })
  await page.goto('/cards')
  await expect(page.getByRole('button', { name: 'Mark as paid' })).toHaveCount(0)
})


test('payment dialog stays within a mobile viewport and Escape cancels', async ({ page }) => {
  const state = await setup(page)
  await page.setViewportSize({ width: 375, height: 667 })
  await page.goto('/cards')
  await page.getByRole('button', { name: 'Mark as paid' }).click()
  const dialog = page.getByRole('dialog', { name: 'Mark as paid' })
  await dialog.getByLabel('Part amount', { exact: true }).check()
  const bounds = await dialog.boundingBox()
  expect(bounds.x).toBeGreaterThanOrEqual(0)
  expect(bounds.x + bounds.width).toBeLessThanOrEqual(375)
  expect(bounds.y + bounds.height).toBeLessThanOrEqual(667)
  await page.keyboard.press('Escape')
  await expect(dialog).not.toBeVisible()
  expect(state.payments).toHaveLength(0)
})


test('local statement summary prefills every review field and its billing period', async ({ page }) => {
  await setup(page)
  await page.route('**/api/statement-previews', (route) => route.fulfill({ json: {
    ...draft, message: null, warnings: ['Extracted locally without AI.'], rows: [],
    summary: { statement_date: '2026-10-01', due_date: '2026-10-21', total_due: 22471, minimum_due: 449, credit_limit: 391000, reward_point_balance: 18791, last_four_digits: null },
  } }))
  await page.goto('/cards/10?tab=statements')
  await page.locator('input[type=file]').setInputFiles(file)
  for (const [label, value] of [ ['Billing month', '10'], ['Billing year', '2026'], ['Statement date', '2026-10-01'], ['Due date', '2026-10-21'], ['Total outstanding / amount due (₹)', '22471'], ['Minimum due (₹)', '449'], ['Credit limit (₹)', '391000'], ['Reward points', '18791'] ]) {
    await expect(page.getByLabel(label, { exact: true })).toHaveValue(value)
  }
  await expect(page.getByLabel('Save PDF only, without changing balances or spending.')).not.toBeChecked()
})


test('Add Card retains its source PDF without requiring a separate accepted review', async ({ page }) => {
  const state = await setup(page)
  await page.goto('/cards/new')
  await page.locator('input[type=file]').first().setInputFiles(file)
  await expect(page.getByRole('heading', { name: 'Review statement' })).toBeVisible()
  await page.getByRole('button', { name: 'Add card', exact: true }).click()
  await expect(page).toHaveURL(/\/cards\/10\?tab=statements$/)
  await expect(page.getByText('statement.pdf', { exact: true })).toBeVisible()
  await expect(page.getByText('No statements uploaded for this card yet.')).toHaveCount(0)
  expect(state.creates).toHaveLength(1)
  expect(state.imports).toEqual([{ staged: true }])
})

test('canceling the first statement review still retains the uploaded PDF', async ({ page }) => {
  const state = await setup(page)
  await page.goto('/cards/new')
  await page.locator('input[type=file]').first().setInputFiles(file)
  await page.getByRole('button', { name: 'Cancel review' }).click()
  await expect(page.getByText('Your uploaded PDF will be saved in Statements', { exact: false })).toBeVisible()
  await page.getByRole('button', { name: 'Add card', exact: true }).click()
  await expect(page).toHaveURL(/\/cards\/10\?tab=statements$/)
  await expect(page.getByText('statement.pdf', { exact: true })).toBeVisible()
  expect(state.imports).toEqual([{ staged: true }])
})


test('statement transactions use a category column with persistent dropdown editing', async ({ page }) => {
  await setup(page)
  const transaction = { id: 40, transaction_date: '2026-09-03', description: 'Petrol shop', category: 'other', amount: 1000 }
  await page.route('**/api/cards/10/statements', (route) => route.fulfill({ json: [{ id: 11, original_filename: 'statement.pdf', billing_month: 10, billing_year: 2026, total_due: 10000, analysis_status: 'completed', transactions: [transaction] }] }))
  const updates = []
  await page.route('**/api/statements/11/transactions/40/category', (route) => {
    updates.push(route.request().postDataJSON())
    transaction.category = updates.at(-1).category
    return route.fulfill({ json: transaction })
  })
  await page.goto('/cards/10?tab=statements')
  await page.getByRole('button', { name: '1 transactions' }).click()
  await expect(page.getByRole('columnheader', { name: 'Category', exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Edit category for Petrol shop' }).click()
  const select = page.getByRole('combobox', { name: 'Category for Petrol shop' })
  await expect(select.getByRole('option', { name: 'Medicine', exact: true })).toHaveCount(1)
  await select.selectOption('fuel')
  await page.getByRole('button', { name: 'Save', exact: true }).click()
  await expect(page.getByRole('button', { name: 'Edit category for Petrol shop' })).toContainText('Fuel')
  expect(updates).toEqual([{ category: 'fuel' }])
  await page.getByRole('button', { name: 'Edit category for Petrol shop' }).click()
  await select.selectOption('grocery')
  await page.getByRole('button', { name: 'Cancel', exact: true }).click()
  expect(updates).toHaveLength(1)
  await page.reload()
  await page.getByRole('button', { name: '1 transactions' }).click()
  await expect(page.getByRole('button', { name: 'Edit category for Petrol shop' })).toContainText('Fuel')
})


test('background statement refresh keeps category editing and table position stable', async ({ page }) => {
  await setup(page)
  const transaction = { id: 40, transaction_date: '2026-09-03', description: 'Petrol shop', category: 'other', amount: 1000 }
  const data = [{ id: 11, original_filename: 'statement.pdf', billing_month: 10, billing_year: 2026, total_due: 10000, analysis_status: 'completed', transactions: [transaction] }]
  let release
  let requests = 0
  let delayRefresh = false
  await page.route('**/api/cards/10/statements', async (route) => {
    requests++
    if (delayRefresh) await new Promise((resolve) => { release = resolve })
    await route.fulfill({ json: data })
  })
  await page.goto('/cards/10?tab=statements')
  await page.getByRole('button', { name: '1 transactions' }).click()
  await page.getByRole('button', { name: 'Edit category for Petrol shop' }).click()
  const select = page.getByRole('combobox', { name: 'Category for Petrol shop' })
  await select.selectOption('fuel')
  const before = await select.boundingBox()
  const baseline = requests
  delayRefresh = true
  await page.evaluate(() => window.dispatchEvent(new Event('sync-data')))
  await expect.poll(() => requests).toBe(baseline + 1)
  await expect(page.getByText('Loading statements…')).toHaveCount(0)
  await expect(select).toHaveValue('fuel')
  const during = await select.boundingBox()
  expect(during.y).toBe(before.y)
  release()
  await expect(select).toHaveValue('fuel')
})

test('background card refresh preserves an open partial-payment dialog', async ({ page }) => {
  await setup(page)
  let release
  let reads = 0
  let delayRefresh = false
  await page.route('**/api/cards', async (route) => {
    reads++
    if (delayRefresh) await new Promise((resolve) => { release = resolve })
    await route.fulfill({ json: [original] })
  })
  await page.goto('/cards')
  await page.getByRole('button', { name: 'Mark as paid' }).click()
  const dialog = page.getByRole('dialog', { name: 'Mark as paid' })
  await dialog.getByLabel('Part amount', { exact: true }).check()
  await dialog.getByLabel('Amount paid (₹)').fill('2500')
  const baseline = reads
  delayRefresh = true
  await page.evaluate(() => window.dispatchEvent(new Event('sync-data')))
  await expect.poll(() => reads).toBe(baseline + 1)
  await expect(dialog).toBeVisible()
  await expect(dialog.getByLabel('Amount paid (₹)')).toHaveValue('2500')
  release()
  await expect(dialog).toBeVisible()
})

test('queued PDF analysis reuses the ready preview for Add Card and its first statement', async ({ page }) => {
  await setup(page)
  let previews = 0, polls = 0
  await page.route('**/api/cards/analyze', (route) => route.fulfill({ status: 202, json: { preview_id: draft.preview_id, status: 'processing', card: {} } }))
  await page.route(`**/api/statement-previews/${draft.preview_id}`, (route) => {
    polls++
    return route.fulfill({ json: { ...draft, status: 'ready', source: 'ai', document_recognized: true, card: { ...original, card_name: 'AI Octane', reward_point_balance: 18791 } } })
  })
  await page.route('**/api/statement-previews', (route) => { previews++; return route.fulfill({ json: draft }) })
  await page.goto('/cards/new')
  await page.locator('input[type=file]').first().setInputFiles(file)
  await expect(page.getByRole('heading', { name: 'Review statement' })).toBeVisible()
  await expect(page.locator('[name=card_name]')).toHaveValue('AI Octane')
  await expect(page.locator('[name=reward_point_balance]')).toHaveValue('18791')
  expect(polls).toBe(1)
  expect(previews).toBe(0)
})
