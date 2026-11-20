import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  workers: 1,
  timeout: 45000,
  expect: {
    timeout: 10000,
  },
  reporter: 'list',
  use: {
    baseURL: 'http://127.0.0.1:8000',
    extraHTTPHeaders: {
      'Accept': 'application/json',
    },
    trace: 'on-first-retry',
  },
  webServer: {
    command: 'php artisan serve --port=8000',
    url: 'http://127.0.0.1:8000/api/offres',
    reuseExistingServer: !process.env.CI,
    timeout: 30000,
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'], channel: 'chrome' },
    },
  ],
});
