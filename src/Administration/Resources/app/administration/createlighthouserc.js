/**
 * @package admin
 */

/* eslint-disable no-console */
const fs = require('fs');
const puppeteer = require('puppeteer');
const fse = require('fs-extra');
const path = require('path');

// just testing
const APP_URL = process.env.APP_URL;
const PROJECT_ROOT = process.env.PROJECT_ROOT;
const DD_API_KEY = process.env.DD_API_KEY;
const LH_PORT = process.env.LH_PORT ?? 8041;
const LH_URL = process.env.LH_URL;
const LH_TOKEN = process.env.LH_ADMIN_TOKEN;

console.log('cache:', process.env.SHOPWARE_HTTP_CACHE_ENABLED);

let testCases;

if (!APP_URL) {
    throw new Error('The environment variable "APP_URL" have to be defined.');
}

if (!PROJECT_ROOT) {
    throw new Error('The environment variable "PROJECT_ROOT" have to be defined.');
}

if (!DD_API_KEY) {
    // eslint-disable-next-line no-console
    console.warn('' +
      'WARNING: The environment variable "DD_API_KEY" has to be defined. ' +
      'Otherwise it can\'t send metrics to datadog.');
}

/**
 *
 * @param browser Browser
 * @returns {Promise<void>}
 */
async function login(browser) {
    // eslint-disable-next-line no-console
    console.log('LOGIN');
    const page = await browser.newPage();

    await page.setViewport({ width: 1920, height: 1080 });
    await page.goto(`${APP_URL}/admin`);

    const usernameInput = await page.$('#sw-field--username');
    const passwordInput = await page.$('#sw-field--password');
    const loginButton = await page.$('button.sw-login__login-action');

    await usernameInput.type('admin');
    await passwordInput.type('shopware');
    await loginButton.click();

    await page.waitForNavigation();
    await page.waitForSelector('.sw-dashboard-index__welcome-message');

    await page.close();
}

async function getDetail(browser) {
    console.log('GET DETAIL URL');
    const page = await browser.newPage();

    await page.goto(`${APP_URL}/admin#/sw/product/index`);
    await page.waitForNavigation();
    await page.waitForFunction(() => !document.querySelector('.sw-loader'));
    await page.waitForFunction(() => !document.querySelector('.sw-skeleton'));

    await page.waitForSelector('.sw-data-grid__row--0');
    await page.click('.sw-data-grid__row--0 a');

    const url = page.url();
    await page.close();

    return url;
}

async function main() {
    const browser = await puppeteer.launch({
        args: [
            `--remote-debugging-port=${LH_PORT}`,
            '--no-sandbox',
            '--disable-setuid-sandbox',
        ],
        // For debugging uncomment next line:
        // headless: false,
    });

    await login(browser);
    const detailUrl = await getDetail(browser);
    console.log('DETAIL URL', detailUrl);

    // Test cases for lighthouse
    testCases = {
        _initial: `${APP_URL}/admin#/sw/dashboard/index`,
        dashboard: `${APP_URL}/admin#/sw/dashboard/index`,
        productListing: `${APP_URL}/admin#/sw/product/index`,
        productDetail: detailUrl,
    };

    /**
     * Add query param to URL because Lighthouse CI don't support
     * SPA yet: https://github.com/GoogleChrome/lighthouse-ci/issues/797
     */
    Object.entries(testCases).forEach(([key, entry]) => {
        const testCaseUrl = new URL(entry);
        testCaseUrl.search = key;

        testCases[key] = testCaseUrl.toString();
    });

    console.log('TEST CASES', testCases);

    // Close browser when all tests are finished
    await browser.close();

    return {
        urlMap: {
            ...testCases,
        },
        general: {
            ci: {
                collect: {
                    url: Object.values(testCases),
                    puppeteerScript: './lighthouse-puppeteer.js',
                    puppeteerLaunchOptions: {
                        args: [
                            '--allow-no-sandbox-job',
                            '--allow-sandbox-debugging',
                            '--no-sandbox',
                            '--disable-gpu',
                            '--disable-gpu-sandbox',
                            '--display',
                        ],
                    },
                    settings: {
                        port: LH_PORT,
                        chromeFlags: '--no-sandbox',
                        disableStorageReset: true,
                        output: 'html',
                        formFactor: 'desktop',
                        screenEmulation: {
                            mobile: false,
                            width: 1920,
                            height: 1080,
                        },
                        throttlingMethod: 'simulate',
                        throttling: {
                            cpuSlowdownMultiplier: 0,
                            downloadThroughputKbps: 16000,
                            requestLatencyMs: 50,
                            rttMs: 50,
                            throughputKbps: 16000,
                            uploadThroughputKbps: 6000,
                        },
                    },
                },
                assert: {
                    // assert options here
                },
                upload: {
                    target: 'lhci',
                    serverBaseUrl: LH_URL,
                    token: LH_TOKEN,
                    ignoreDuplicateBuildFailure: true,
                },
                server: {
                    // server options here
                },
                wizard: {
                    // wizard options here
                },
            },
        },
    };
}

main().then((config) => {
    fse.mkdirpSync(path.join(PROJECT_ROOT, '/build/artifacts/lighthouse-admin-config/'));

    fs.writeFile(
        path.join(
            PROJECT_ROOT,
            '/build/artifacts/lighthouse-admin-config/general.json',
        ),
        JSON.stringify(config.general),
        (err) => {
            if (err) {
                console.error(err);
            }
        // file written successfully
        },
    );

    fs.writeFile(
        path.join(
            PROJECT_ROOT,
            '/build/artifacts/lighthouse-admin-config/urlmap.json',
        ),
        JSON.stringify(config.urlMap),
        err => {
            if (err) {
                console.error(err);
            }
        // file written successfully
        },
    );

    console.log('wrote');
});

