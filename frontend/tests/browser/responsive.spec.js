import { test, expect } from '@playwright/test'

test.use({ serviceWorkers: 'block' })

const card = {
  id: 1,
  card_name: 'Premium travel and everyday rewards credit card',
  bank_name: 'Example Bank',
  last_four_digits: '1234',
  network: 'visa',
  permission: 'owner',
  total_limit: 100000,
  current_outstanding: 10000,
  utilization_percentage: 10,
  utilization_band: 'good',
  statement_day: 15,
  due_day: 1,
  annual_fee_amount: 1000,
  annual_fee_month: 10,
  card_year_start_month: 1,
  waiver_spend_required: 100000,
  waiver_spend_completed: 30000,
  waiver_remaining_spend: 70000,
  waiver_months_left: 3,
  waiver_suggested_monthly_spend: 23333,
  reward_point_balance: 1000,
  reward_point_value_estimate: 0.5,
  reward_rate_general: 2,
  cashback_cap_amount: 1000,
  forex_markup_percent: 2,
  fuel_surcharge_waiver_percent: 1,
  insurance_cover_amount: 100000,
  lounge_access: true,
  best_categories: ['travel', 'online'],
}
const cards = [card, { ...card, id: 2, card_name: 'Everyday cashback', last_four_digits: '5678' }]
const benefit = {
  id: 1, title: 'Complimentary international airport lounge access', type: 'lounge',
  frequency: 'quarterly', total_allowed: 4, used_count: 1, remaining: 3, estimated_value: 5000,
}
const scores = cards.map((c) => ({
  card_id: c.id, card_name: c.card_name, total_score: 42,
  breakdown: { waiver_urgency_score: 10, reward_category_score: 30, unused_benefit_score: 5,
    utilization_penalty: 2, due_date_risk_penalty: 1 },
}))

test.beforeEach(async ({ page }) => {
  await page.addInitScript(() => localStorage.setItem('ccapp-auth', JSON.stringify({
    state: { token: 'responsive-test', user: { id: 999, name: 'Responsive Test User', role: 'admin' } },
    version: 0,
  })))
  // Use deterministic local fixtures; these layout tests do not modify backend accounts.
  await page.route('**/api/**', async (route) => {
    const path = new URL(route.request().url()).pathname.replace(/^\/api/, '')
    let data
    if (path === '/cards') data = cards
    else if (path === '/cards/1') data = card
    else if (path.endsWith('/benefits')) data = [benefit]
    else if (path.endsWith('/statements')) data = [{
      id: 1, billing_month: 10, billing_year: 2026, analysis_status: 'completed',
      original_filename: `${'monthly-statement-'.repeat(6)}.pdf`, total_due: 10000,
      transactions: [{ id: 1, transaction_date: '2026-10-01',
        description: 'International airline purchase and additional travel charges', category: 'travel', amount: 10000 }],
    }]
    else if (path === '/dashboard') data = {
      overall_utilization: { percentage: 10, band: 'good' },
      upcoming_dates: [{ card_name: card.card_name, type: 'due', date: '2026-10-01', days_until: 2 }],
      waiver_alerts: [{ card_id: 1, card_name: card.card_name, remaining_spend: 70000,
        months_left: 3, suggested_monthly_spend: 23333 }], unused_benefits: [benefit],
    }
    else if (path.startsWith('/recommendation')) data = scores
    else if (path === '/comparison') data = cards
    else if (path === '/reports/monthly') data = {
      total_spend: 10000, potential_missed_rewards_value: 200, best_card_used: card,
      waiver_progress: [{ card_id: 1, card_name: card.card_name, waiver_progress_percentage: 30 }],
      utilization_status: [{ card_id: 1, card_name: card.card_name, utilization_percentage: 10, band: 'good' }],
      unused_benefits: [benefit],
    }
    else if (path === '/reports/spend-by-category') data = {
      from: { month: 5, year: 2026 }, to: { month: 10, year: 2026 }, total_spend: 10000,
      categories: [{ category: 'travel', amount: 7000, percentage: 70 },
        { category: 'online', amount: 3000, percentage: 30 }],
    }
    else if (path === '/admin/users') data = {
      data: [{ id: 999, name: 'Responsive Test User', email: `${'long-email-'.repeat(5)}@example.test`, role: 'admin' }],
      last_page: 1,
    }
    else if (path === '/admin/audit') data = { data: [], current_page: 1 }
    else if (path === '/admin/health') data = { database: 'ok', storage_writable: true, ai_configured: false }
    else if (path.endsWith('/messages/preview')) data = {
      preview_id: 'test-preview', summary: {},
      rows: [{ transaction_date: '2026-10-01', amount: 500, description: 'Grocery purchase',
        direction: 'purchase', category: 'grocery', warnings: [] }],
    }
    else data = []
    await route.fulfill({
      json: data,
      headers: {
        'access-control-allow-origin': '*',
        'access-control-allow-methods': 'GET, POST, OPTIONS',
        'access-control-allow-headers': 'Authorization, Content-Type',
      },
    })
  })
})

async function expectFits(page) {
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth))
    .toBeLessThanOrEqual((await page.viewportSize()).width + 1)
  // Form fields should fit their containers as well as the viewport.
  const overflow = await page.locator('input, select, textarea').evaluateAll((fields) => fields
    .filter((field) => field.getClientRects().length && !field.closest('table'))
    .filter((field) => {
      const rect = field.getBoundingClientRect()
      return rect.left < 0 || rect.right > window.innerWidth + 1
    }).map((field) => field.outerHTML))
  expect(overflow).toEqual([])
}

for (const width of [320, 390, 768, 1024, 1440]) {
  test(`pages fit a ${width}px viewport`, async ({ page }) => {
    await page.setViewportSize({ width, height: 900 })
    const pages = [
      ['/', 'Overall utilization'], ['/cards', card.card_name], ['/cards/new', 'Card name'],
      ['/cards/import', 'Bulk Upload Cards'], ['/cards/1', 'Total limit'],
      ['/recommendation', '42.0 pts'], ['/benefits', benefit.title], ['/calendar', 'Statement date'],
      ['/reports', 'Potential missed rewards this month:'], ['/comparison', card.card_name],
      ['/spend-analyzer', 'All Categories'], ['/sync', 'Enable offline storage'],
      ['/sharing', 'Manage an owned card'], ['/import-messages', 'Destination card'],
      ['/admin', 'System health'], ['/development-ledger', 'Scoped features'],
    ]
    for (const [url, ready] of pages) {
      await test.step(url, async () => {
        await page.goto(url)
        await expect(page.getByText(ready, { exact: false }).first()).toBeVisible()
        await expectFits(page)
        if (url === '/cards/1') {
          await page.getByRole('button', { name: 'Statements', exact: true }).click()
          await page.getByRole('button', { name: '1 transactions' }).click()
          await expectFits(page)
        }
        if (url === '/calendar') {
          await page.getByRole('button', { name: /^Day 1(?:,|$)/ }).click()
          await expect(page.getByText('•••• 1234', { exact: false }).first()).toBeVisible()
          await expectFits(page)
          await page.getByRole('button', { name: 'List', exact: true }).click()
          await expectFits(page)
        }
        if (url === '/comparison') {
          await page.getByRole('checkbox').first().check()
          await page.getByRole('checkbox').nth(1).check()
          await expect(page.getByRole('table')).toBeVisible()
          await expectFits(page)
          if (width < 640) {
            const scroller = page.getByRole('table').locator('..')
            expect(await scroller.evaluate((el) => el.scrollWidth > el.clientWidth)).toBeTruthy()
            expect(await scroller.evaluate((el) => { el.scrollLeft = 100; return el.scrollLeft })).toBeGreaterThan(0)
          }
        }
        if (url === '/benefits') {
          await page.getByRole('button', { name: 'Add Benefit', exact: true }).click()
          await expect(page.getByRole('button', { name: 'Add benefit', exact: true })).toBeVisible()
          await expectFits(page)
        }
        if (url === '/spend-analyzer') {
          await page.getByRole('button', { name: 'Grid', exact: true }).click()
          await expectFits(page)
        }
        if (url === '/sharing') {
          await page.getByLabel('Manage an owned card').selectOption('1')
          await expect(page.getByLabel('Recipient email')).toBeVisible()
          await expectFits(page)
        }
        if (url === '/import-messages') {
          await page.getByLabel('Destination card').selectOption('1')
          await page.getByLabel('Message text').fill('INR 500 spent at Grocery Store')
          await page.getByRole('button', { name: 'Preview messages' }).click()
          await expect(page.getByRole('heading', { name: 'Review extracted data' })).toBeVisible()
          await expectFits(page)
        }
      })
    }
    for (const url of ['/login', '/register']) {
      await page.goto(url)
      await expect(page.locator('form')).toBeVisible()
      await expectFits(page)
    }
  })
}

test('mobile navigation opens, supports keyboard dismissal, and closes after navigation', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/cards')
  const nav = page.getByRole('navigation', { name: 'Main navigation' })
  await expect(nav).toBeHidden()
  await page.getByRole('button', { name: 'Menu', exact: true }).click()
  await expect(nav).toBeVisible()
  await expect(page.getByRole('button', { name: 'Close menu' })).toHaveAttribute('aria-expanded', 'true')
  await nav.getByRole('link', { name: 'Calendar', exact: true }).focus()
  await page.keyboard.press('Escape')
  await expect(nav).toBeHidden()
  await page.getByRole('button', { name: 'Menu', exact: true }).click()
  await nav.getByRole('link', { name: 'Calendar', exact: true }).click()
  await expect(page).toHaveURL(/\/calendar$/)
  await expect(nav).toBeHidden()
  await page.setViewportSize({ width: 1280, height: 900 })
  await expect(nav).toBeVisible()
  await expect(page.getByRole('button', { name: 'Menu', exact: true })).toBeHidden()
  const navBounds = await nav.boundingBox()
  const mainBounds = await page.getByRole('main').boundingBox()
  expect(mainBounds.x).toBeGreaterThan(navBounds.x + navBounds.width)
})
