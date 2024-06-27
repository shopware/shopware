import { defineConfig, devices } from '@playwright/test';
import path from 'path';
import dotenv from 'dotenv';

// Read from default ".env" file.
dotenv.config();

const missingEnvVars = ['APP_URL'].filter((envVar) => {
    return process.env[envVar] === undefined;
});

if (missingEnvVars.length > 0) {
    const envPath = path.resolve('.env');

    process.stdout.write(`Please provide the following env vars (loaded env: ${envPath}):\n`);
    process.stdout.write('- ' + missingEnvVars.join('\n- ') + '\n');

    process.exit(1);
}

process.env['SHOPWARE_ADMIN_USERNAME'] = process.env['SHOPWARE_ADMIN_USERNAME'] || 'admin';
process.env['SHOPWARE_ADMIN_PASSWORD'] = process.env['SHOPWARE_ADMIN_PASSWORD'] || 'shopware';

// make sure APP_URL ends with a slash
process.env['APP_URL'] = process.env['APP_URL'].replace(/\/+$/, '') + '/';
if (process.env['ADMIN_URL']) {
    process.env['ADMIN_URL'] = process.env['ADMIN_URL'].replace(/\/+$/, '') + '/';
} else {
    process.env['ADMIN_URL'] = process.env['APP_URL'] + 'admin/';
}

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
    testDir: './tests',
    fullyParallel: true,

    /* Fail the build on CI if you accidentally left test.only in the source code. */
    forbidOnly: !!process.env.CI,
    /* Retry on CI only */
    retries: process.env.CI ? 2 : 0,
    /* There are still some issues with running the tests in parallel */
    workers: process.env.CI ? 1 : 1,

    reporter: 'html',

    timeout: 60000,

    /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
    use: {
        /* Base URL to use in actions like `await page.goto('/')`. */
        baseURL: process.env['APP_URL'],
        trace: 'on',
        video: 'off',
    },

    // We abuse this to wait for the external webserver
    webServer: {
        command: 'sleep 1d',
        url: process.env['APP_URL'],
        reuseExistingServer: true,
    },

    /* Configure projects for major browsers */
    projects: [
        {
            name: 'Setup',
            use: {
                ...devices['Desktop Chrome'],
            },
            grep: /@Setup/,
        },
        {
            name: 'Platform',
            use: {
                ...devices['Desktop Chrome'],
            },
            dependencies: ['Setup'],
            grepInvert: /@Install|@Update|@Setup.*/,
        },
        {
            name: 'Install',
            use: {
                ...devices['Desktop Chrome'],
            },
            grep: /@Install/,
            retries: 0,
        },
        {
            name: 'Update',
            use: {
                ...devices['Desktop Chrome'],
            },
            dependencies: [],
            grep: /@Update/,
            retries: 0,
        },
    ],

    /* Run your local dev server before starting the tests */
    // webServer: {
    //   command: 'npm run start',
    //   url: 'http://127.0.0.1:3000',
    //   reuseExistingServer: !process.env.CI,
    // },
});
