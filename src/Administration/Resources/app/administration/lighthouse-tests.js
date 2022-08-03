/* eslint-disable no-console */
const fse = require('fs-extra');
const path = require('path');
const puppeteer = require('puppeteer');
const lighthouse = require('lighthouse');
const axios = require('axios');
const _get = require('lodash/get');

const APP_URL = process.env.APP_URL;
const PROJECT_ROOT = process.env.PROJECT_ROOT;
const DD_API_KEY = process.env.DD_API_KEY;
const LH_PORT = process.env.LH_PORT ?? 8041;


if (!APP_URL) {
    throw new Error('The environment variable "APP_URL" have to be defined.');
}

if (!PROJECT_ROOT) {
    throw new Error('The environment variable "PROJECT_ROOT" have to be defined.');
}

if (!DD_API_KEY) {
    // eslint-disable-next-line no-console
    console.warn('' +
        'WARNING: The environment variable "DD_API_KEY" have to defined. ' +
        'Otherwise it can\'t send metrics to datadog.');
}

/**
 *
 * @param browser Browser
 * @returns {Promise<void>}
 */
async function login(browser) {
    console.log('LOGIN');
    const page = await browser.newPage();

    await page.setViewport({ width: 1280, height: 768 });
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

async function iterateAsync(arr, handler) {
    await arr.reduce(async (promise, value) => {
        // This line will wait for the last async function to finish.
        // The first iteration uses an already resolved Promise
        // so, it will immediately continue.
        await promise;

        await handler(value);
    }, Promise.resolve());
}

function getTimeStamp() {
    return `${Math.floor(new Date().getTime() / 1000)}`;
}

function getScriptsSize(jsReport) {
    return jsReport.audits['network-requests'].details.items
        .filter((asset) => asset.resourceType === 'Script')
        .reduce((totalSize, asset) => {
            return totalSize + asset.resourceSize;
        }, 0);
}


async function sendMetrics(metrics) {
    console.log('SEND METRICS');

    const METRIC_SCORE_MAP = {
        // General scores
        performance: 'categories.performance.score',
        accessibility: 'categories.accessibility.score',
        seo: 'categories.seo.score',
        best_practices: 'categories.["best-practices"].score',
        pwa: 'categories.pwa.score',
        // Performance breakdown
        first_contentful_paint: 'audits["first-contentful-paint"].numericValue',
        speed_index: 'audits["speed-index"].numericValue',
        largest_contentful_paint: 'audits["largest-contentful-paint"].numericValue',
        time_to_interactive: 'audits["interactive"].numericValue',
        total_blocking_time: 'audits["total-blocking-time"].numericValue',
        cumulative_layout_shift: 'audits["cumulative-layout-shift"].numericValue',
        server_response_time: 'audits["server-response-time"].numericValue'
    };
    const timeStamp = getTimeStamp();

    const series = metrics.reduce((acc, metric) => {
        acc.push(...Object.entries(METRIC_SCORE_MAP).map(([metricName, scorePath]) => {
            return {
                host: 'lighthouse',
                type: 'gauge',
                metric: `lighthouse.${metricName}.${metric.testName}`,
                points: [[timeStamp, _get(metric.result.lhr, scorePath)]],
            };
        }));
        acc.push({
            host: 'lighthouse',
            type: 'gauge',
            metric: `lighthouse.total_bundle_size.${metric.testName}`,
            points: [[timeStamp, getScriptsSize(metric.result.lhr)]],
        });

        return acc;
    }, []);

    if (!DD_API_KEY) return undefined;

    return axios({
        method: 'post',
        url: 'https://api.datadoghq.eu/api/v1/series',
        headers: {
            'Content-Type': 'application/json',
            'DD-API-KEY': DD_API_KEY,
        },
        data: {
            series,
        },
    });
}

async function main() {
    // create folder for artifacts
    fse.mkdirpSync(path.join(PROJECT_ROOT, '/build/artifacts/lighthouse-results/'));

    const PORT = LH_PORT;

    const browser = await puppeteer.launch({
        args: [
            `--remote-debugging-port=${PORT}`,
            '--no-sandbox',
            '--disable-setuid-sandbox',
        ],
        // For debugging:
        // headless: false,
        // slowMo: 0,
    });

    // Login into the admin so that we don't get redirected to login page
    await login(browser);

    // Test cases for lighthouse
    const testCases = {
        // Initial test is not representative. It is just for warming up the backend caches.
        _initial: async () => `${APP_URL}/admin#/sw/dashboard/index`,
        dashboard: async () => `${APP_URL}/admin#/sw/dashboard/index`,
        productListing: async () => `${APP_URL}/admin#/sw/product/index`,
        productDetail: async () => {
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
        },
    };

    // Execute lighthouse tests
    const lighthouseTests = [];

    await iterateAsync(Object.entries(testCases), async ([testName, getTestUrl]) => {
        console.log('MEASURE ', testName);
        const url = await getTestUrl();
        const result = await lighthouse(url, {
            port: PORT,
            disableStorageReset: true,
            output: 'html',
            formFactor: 'desktop',
            screenEmulation: {
                mobile: false,
                width: 1360,
                height: 768,
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
        });

        // Don't report results for the initial test
        if (testName === '_initial') {
            return;
        }

        lighthouseTests.push({
            testName: testName,
            result: result,
        });
    });

    // Save the result in files
    lighthouseTests.forEach(({ testName, result }) => {
        fse.outputFileSync(
            path.join(PROJECT_ROOT, `/build/artifacts/lighthouse-results/${testName}.html`),
            result.report,
        );

        // Output the result
        console.log('-----');
        console.log(`Report is written for "${testName}"`);
        console.log('Performance score was', result.lhr.categories.performance.score * 100);
    });

    // Send results to dataDog
    await sendMetrics(lighthouseTests);

    // Close browser when all tests are finished
    await browser.close();
}

main();
