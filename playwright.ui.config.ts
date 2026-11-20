import { defineConfig, devices } from '@playwright/test';
export default defineConfig({
    testDir: './e2e',
    testMatch: 'ui-workflows.spec.ts',
    workers: 1,
    timeout: 90000,
    expect: { timeout: 15000 },
    reporter: [['list'], ['html', { outputFolder: 'playwright-report', open: 'never' }]],
    use: {
        baseURL: 'http://127.0.0.1:8000',
        actionTimeout: 10000,
        navigationTimeout: 20000,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        ...devices['Desktop Chrome'],
        channel: 'chrome',
    },
    webServer: {
        command: 'php artisan serve --no-reload --host=127.0.0.1 --port=8000',
        env: { PHP_CLI_SERVER_WORKERS: '4' },
        url: 'http://127.0.0.1:8000/up',
        reuseExistingServer: true,
    },
});
