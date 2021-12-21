/* eslint-disable */

// ***********************************************************
// This example plugins/index.js can be used to load plugins
//
// You can change the location of this file or turn off loading
// the plugins file with the 'pluginsFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/plugins-guide
// ***********************************************************

// This function is called when a project is opened or re-opened (e.g. due to
// the project's config changing)

const axios = require('axios');
require('@babel/register');

// TODO Check incompatibility and reintegrate as soon as possible
// const logToOutput = require('cypress-log-to-output');

module.exports = (on, config) => {
    // logToOutput.install(on);

    // `on` is used to hook into various events Cypress emits

    // register cypress-grep plugin code
    require('cypress-grep/src/plugin')(config)

    // TODO: Workaround to cypress issue #6540, remove as soon as it's fixed
    on('before:browser:launch', (browser, launchOptions) => {
        if (browser.name === 'chrome' && browser.isHeadless) {
            launchOptions.args.push('--disable-gpu');

            return launchOptions;
        }
    });

    on('before:browser:launch', () => {
        config.env.projectRoot = config.env.projectRoot || config.env.shopwareRoot;
    });

    // report flaky tests
    on('after:spec', (spec, results) => {
        if (!results) {
            return;
        }

        // Find all failed tests which contains retry attempts
        const failedTests = results.tests.filter((test) => {
            return test.attempts.some((attempt) => attempt.state === 'failed')
        })

        // stop execution when no failing test exists
        if (failedTests.length <= 0) {
            return;
        }

        // stop execution when a test fails non-flaky
        if (results.stats?.failures > 0) {
            return;
        }

        // stop execution when no environment variable is defined
        if (!config.env['DD_API_KEY']) {
            return Promise.resolve();
        }

        // log each failing test
        const requestForFailingTests = failedTests.map(failedTest => {
            const failedAttempts = failedTest.attempts.filter(a => a.state === 'failed').length;

            // report retried tests to datadog
            return axios({
                method: 'post',
                url: 'https://http-intake.logs.datadoghq.eu/v1/input',
                headers: {
                    'DD-API-KEY': config.env['DD_API_KEY']
                },
                data: {
                    ddsource: 'cypress-admin',
                    ddtags: 'cypress:admin,cypress:retries',
                    message: 'Potential flaky e2e test in admin',
                    service: 'Cypress',
                    'test-description': failedTest?.title?.[0],
                    'test-it': failedTest?.title?.[1],
                    'test-retries': failedAttempts,
                    'test-target-branch': config.env['TARGET_BRANCH'],
                    'test-target-commit': config.env['TARGET_COMMIT'],
                    'test-commit-branch': config.env['COMMIT_BRANCH'],
                }
            })
        })

        // Directly return Promise.all creates an error. Therefore I need to wrap the promise all
        // in a separate Promise call.
        return new Promise((resolve, reject) => {
            Promise.all([...requestForFailingTests])
                .then(() => resolve())
                .catch((e) => reject(e));
        });
    })
};
