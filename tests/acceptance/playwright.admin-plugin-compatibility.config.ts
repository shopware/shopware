import { defineConfig, devices } from '@playwright/test';
import dotenv from 'dotenv';
import path from 'path';

dotenv.config();

const ciGuardVariables = [
    'CI',
    'GITHUB_ACTIONS',
    'GITLAB_CI',
].filter((name) => Object.prototype.hasOwnProperty.call(process.env, name));

if (ciGuardVariables.length > 0) {
    process.stderr.write(`The Admin plugin compatibility smoke suite is local-only. Unset: ${ciGuardVariables.join(', ')}\n`);
    process.exit(1);
}

const missingEnvVars = ['APP_URL'].filter((envVar) => process.env[envVar] === undefined);

if (missingEnvVars.length > 0) {
    const envPath = path.resolve('.env');

    process.stdout.write(`Please provide the following env vars (loaded env: ${envPath}):\n`);
    process.stdout.write(`- ${missingEnvVars.join('\n- ')}\n`);
    process.exit(1);
}

process.env.SHOPWARE_ADMIN_USERNAME = process.env.SHOPWARE_ADMIN_USERNAME || 'admin';
process.env.SHOPWARE_ADMIN_PASSWORD = process.env.SHOPWARE_ADMIN_PASSWORD || 'shopware';

const ignoreHTTPSErrors = process.env.SHOPWARE_PLAYWRIGHT_IGNORE_HTTPS_ERRORS === 'true' ||
    process.env.SHOPWARE_PLAYWRIGHT_IGNORE_HTTPS_ERRORS === '1';

process.env.APP_URL = process.env.APP_URL.replace(/\/+$/, '') + '/';
process.env.ADMIN_URL = process.env.ADMIN_URL ?
    process.env.ADMIN_URL.replace(/\/+$/, '') + '/' :
    `${process.env.APP_URL}admin/`;

export default defineConfig({
    testDir: './admin-plugin-compatibility',
    fullyParallel: false,
    forbidOnly: true,
    retries: 0,
    workers: 1,
    reporter: 'list',
    timeout: 60_000,
    use: {
        baseURL: process.env.APP_URL,
        trace: 'retain-on-failure',
        video: 'off',
        ignoreHTTPSErrors,
    },
    webServer: {
        command: 'sleep 1d',
        url: process.env.APP_URL,
        reuseExistingServer: true,
        ignoreHTTPSErrors,
    },
    projects: [
        {
            name: 'Admin Plugin Compatibility',
            use: {
                ...devices['Desktop Chrome'],
            },
        },
    ],
});
