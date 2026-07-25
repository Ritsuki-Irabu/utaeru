import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
    testDir: './e2e',
    fullyParallel: true,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 2 : 0,
    reporter: process.env.CI ? 'github' : 'list',
    use: {
        baseURL: 'http://127.0.0.1:5173',
        trace: 'retain-on-failure',
        screenshot: 'on',
        video: 'on',
    },
    webServer: {
        command: 'npm run dev -- --host 0.0.0.0',
        cwd: '.',
        url: 'http://127.0.0.1:5173',
        reuseExistingServer: !process.env.CI,
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
})
