import { defineConfig, devices } from '@playwright/test';
import path from 'path';
import fs from 'fs';
import dotenv from 'dotenv';
import { AdminApiContext } from '@fixtures/AdminApiContext';

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

const projectRoot = path.resolve('./../../');
const pluginFile = path.resolve(projectRoot, 'var/plugins.json');



interface PluginInfo {
    basePath: string;
    technicalName: string;
}

function getPluginProjects() {
    if (!fs.existsSync(pluginFile)) {
        // look for plugins in custom/plugins and test if acceptance tests exist
        const customPlugins = path.resolve(projectRoot, 'custom/plugins');
        const pluginFolders = fs.readdirSync(customPlugins);
        const pluginProjects = pluginFolders.map((pluginFolder) => {
            const pluginPath = path.resolve(customPlugins, pluginFolder);
            const acceptanceTestsPath = path.resolve(pluginPath, 'tests/acceptance/tests');
            if (fs.existsSync(acceptanceTestsPath)) {
                return {
                    name: pluginFolder,
                    use: {
                        ...devices['Desktop Chrome'],
                    },
                    testDir: acceptanceTestsPath,
                };
            }
        }).filter((plugin) => plugin !== undefined);

        return pluginProjects;
    }

    const pluginDefinition = JSON.parse(fs.readFileSync(pluginFile, 'utf8')) as Record<string, PluginInfo>;

    return Object.entries(pluginDefinition)
        .map(([, pluginData]) => {
            const basePath = pluginData.basePath.replace(/\/src\/$/, '');
            return {
                basePath: basePath,
                technicalName: pluginData.technicalName,
                acceptanceTestsPath: path.resolve(projectRoot, basePath, 'tests/acceptance/tests'),
            };
        })
        .filter((plugin) => {
            return fs.existsSync(plugin.acceptanceTestsPath);
        })
        .map((plugin) => {
            return {
                name: plugin.technicalName,
                use: {
                    ...devices['Desktop Chrome'],
                },
                testDir: plugin.acceptanceTestsPath,
            };
        });
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

    /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
    use: {
        /* Base URL to use in actions like `await page.goto('/')`. */
        baseURL: process.env['APP_URL'],
        trace: 'on',
        video: 'off',
    },

    // we abuse this to wait for the external webserver
    webServer: {
        command: 'sleep 1d',
        url: process.env['APP_URL'],
        reuseExistingServer: true,
    },

    /* Configure projects for major browsers */
    projects: [
        {
            name: 'Platform',
            use: {
                ...devices['Desktop Chrome'],
            },
            grepInvert: /@install|@update/,
        },
        {
            name: 'Install',
            use: {
                ...devices['Desktop Chrome'],
            },
            grep: /@install/,
            retries: 0,
        },
        {
            name: 'Update',
            use: {
                ...devices['Desktop Chrome'],
            },
            grep: /@update/,
            retries: 0,
        },

        ...getPluginProjects(),

        /**
         * Uncomment other browsers after prototype!
         */
        // {
        //   name: 'firefox',
        //   use: { ...devices['Desktop Firefox'] },
        // },
        //
        // {
        //   name: 'webkit',
        //   use: { ...devices['Desktop Safari'] },
        // },

        /* Test against mobile viewports. */
        // {
        //   name: 'Mobile Chrome',
        //   use: { ...devices['Pixel 5'] },
        // },
        // {
        //   name: 'Mobile Safari',
        //   use: { ...devices['iPhone 12'] },
        // },

        /* Test against branded browsers. */
        // {
        //   name: 'Microsoft Edge',
        //   use: { ...devices['Desktop Edge'], channel: 'msedge' },
        // },
        // {
        //   name: 'Google Chrome',
        //   use: { ...devices['Desktop Chrome'], channel: 'chrome' },
        // },
    ],

    /* Run your local dev server before starting the tests */
    // webServer: {
    //   command: 'npm run start',
    //   url: 'http://127.0.0.1:3000',
    //   reuseExistingServer: !process.env.CI,
    // },
});
