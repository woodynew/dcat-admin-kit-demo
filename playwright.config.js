import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:18084';
const channel = process.env.PLAYWRIGHT_CHANNEL ?? (process.env.CI ? 'chromium' : 'chrome');
const php = process.env.PHP_BINARY ?? 'php';

export default defineConfig({
    testDir: './tests/Browser',
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    // Retrying a write can conceal a failure or duplicate shared records.
    retries: 0,
    timeout: 45_000,
    expect: { timeout: 10_000 },
    reporter: [['list'], ['html', { open: 'never' }]],
    use: {
        baseURL,
        ...devices['Desktop Chrome'],
        channel,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    webServer: process.env.PLAYWRIGHT_BASE_URL ? undefined : {
        command: `'${php.replaceAll("'", "'\\''")}' artisan serve --host=127.0.0.1 --port=18084`,
        url: baseURL,
        reuseExistingServer: !process.env.CI,
        timeout: 60_000,
    },
});
