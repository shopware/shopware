import { defineConfig, devices } from '@playwright/test';
import path from "path";
import fs from "fs";

const env = require('dotenv').config({
    path: '.env',
});

const missingEnvVars = ['APP_URL', 'SHOPWARE_ACCESS_KEY_ID', 'SHOPWARE_SECRET_ACCESS_KEY'].filter((envVar) => {
    return process.env[envVar] === undefined;
})

if (missingEnvVars.length > 0) {
    const envPath = path.resolve('.env');

    process.stdout.write(`Please provide the following env vars (loaded env: ${envPath}):\n`);
    process.stdout.write('- ' + missingEnvVars.join('\n- ') + '\n');

    if (missingEnvVars.includes('SHOPWARE_ACCESS_KEY_ID') || missingEnvVars.includes('SHOPWARE_SECRET_ACCESS_KEY')) {
        process.stdout.write('\nTo generate the integration you can use `bin/console framework:integration:create AcceptanceTestSuite --admin`');
    }

    process.exit(1);
}

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
    testDir: './tests',
    /* Run tests in files in parallel */
    fullyParallel: true,
    /* Fail the build on CI if you accidentally left test.only in the source code. */
    forbidOnly: !!process.env.CI,
    /* Retry on CI only */
    retries: process.env.CI ? 2 : 0,
    /* There are still some issues with running the tests in parallel */
    workers: process.env.CI ? 1 : 1,
    /* Reporter to use. See https://playwright.dev/docs/test-reporters */
    reporter: 'html',
    /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */

    use: {
        /* Base URL to use in actions like `await page.goto('/')`. */
        baseURL: process.env['APP_URL'],

        /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
        trace: 'retain-on-failure',
        video: 'on-first-retry',
        screenshot: 'only-on-failure',
    },

    /* Configure projects for major browsers */
    projects: [
        {
            name: 'Platform',
            use: {
                ...devices['Desktop Chrome'],
            }
        },

        ...(() => {
            /**
             * TODO: only run the tests if the plugin is active on the target system
             *
             * We could use an auto fixture to check for plugin availability and use testInfo to skip the test
             */

            const projectRoot = path.resolve('./../../')
            const pluginFile = path.resolve(projectRoot, 'var/plugins.json');

            if (!fs.existsSync(pluginFile)) {
                console.warn('No plugins.json file found. Skipping plugin tests.');
                return [];
            }

            const pluginDefinition = JSON.parse(fs.readFileSync(pluginFile, 'utf8')) as {
                [pluginName: string]: {
                    basePath: string,
                    technicalName: string,
                }
            };

            const pluginProjects = Object.entries(pluginDefinition)
                .map(([pluginName, pluginData]) => {
                    const basePath = pluginData.basePath.replace(/\/src\/$/, '');
                    return {
                        basePath: basePath,
                        technicalName: pluginData.technicalName,
                        acceptanceTestsPath: path.resolve(projectRoot, basePath, 'tests/acceptance/tests'),
                    }
                })
                .filter(plugin => {
                    return fs.existsSync(plugin.acceptanceTestsPath);
                })
                .map(plugin => {
                    return {
                        name: plugin.technicalName,
                        use: {
                            ...devices['Desktop Chrome'],
                        },
                        testDir: plugin.acceptanceTestsPath,
                    }
                });

            return pluginProjects;
        })(),

        /**
         * Uncomment other brothers after prototype!
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
