import { defineConfig } from '@playwright/test'
export default defineConfig({
  testDir: './tests/browser',
  workers: 1,
  timeout: 60000,
  use: { baseURL: 'http://localhost:5178', headless: true, trace: 'retain-on-failure' },
  webServer: [
    {
      command: 'npm run dev -- --host 127.0.0.1 --port 5178 --strictPort',
      url: 'http://localhost:5178',
      reuseExistingServer: false,
    },
    {
      command: 'npm run preview -- --host 127.0.0.1 --port 4174 --strictPort',
      url: 'http://localhost:4174',
      reuseExistingServer: false,
    },
  ],
})
