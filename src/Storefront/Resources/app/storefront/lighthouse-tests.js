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

async function getDetail(browser) {
    console.log('GET DETAIL URL');
    const page = await browser.newPage();

    await page.setViewport({ width: 1920, height: 1080 });

    await page.goto(`${APP_URL}`);
    const detailButton = await page.$('.product-action a.btn');
    await detailButton.click();

    await page.waitForNavigation();
    await page.waitForSelector('meta[itemprop="productID"]');
    const buyButton = await page.$('.buy-widget-container .btn-buy');

    let productUrl = await page.$eval('meta[property="product:product_link"]', el => el.content);

    await page.close();

    return productUrl;
}


async function getNavUrl(browser) {
    console.log('GET NAV URL');
    const page = await browser.newPage();

    await page.setViewport({ width: 1920, height: 1080 });

    await page.goto(`${APP_URL}`);
    await page.waitForSelector('a.main-navigation-link');

    let secondNavLinkUrl = await page.$eval('a.main-navigation-link:nth-of-type(2n)', el => el.href);

    await page.close();

    return secondNavLinkUrl;
}

/**
 *
 * @param browser Browser
 * @returns {Promise<void>}
 */
async function loginAndFixtures(browser) {
    console.log('LOGIN');
    const page = await browser.newPage();

    await page.setViewport({ width: 1920, height: 1080 });
    await page.goto(`${APP_URL}/account/login`);

    const usernameInput = await page.$('#loginMail');
    const passwordInput = await page.$('#loginPassword');
    const loginButton = await page.$('.login-submit .btn-primary');

    await usernameInput.type('test@example.com');
    await passwordInput.type('shopware');
    await loginButton.click();

    await page.waitForNavigation();
    await page.waitForSelector('.account-welcome');

    console.log('FILL CART');
    await page.goto(`${APP_URL}`);
    const detailButton = await page.$('.product-action a.btn');
    await detailButton.click();

    await page.waitForNavigation();
    await page.waitForSelector('meta[itemprop="productID"]');
    const buyButton = await page.$('.buy-widget-container .btn-buy');

    await buyButton.click();

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
                metric: `lighthouse.storefront.${metricName}.${metric.testName}`,
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
    fse.mkdirpSync(path.join(PROJECT_ROOT, '/build/artifacts/lighthouse-storefront-results/'));

    const PORT = LH_PORT;

    const browser = await puppeteer.launch({
        args: [
            `--remote-debugging-port=${PORT}`,
            '--no-sandbox',
            '--disable-setuid-sandbox',
        ],
        // For debugging uncomment next line:
        // headless: false,
        slowMo: 50,
    });

    const detailUrl = getDetail(browser);
    const navUrl = getNavUrl(browser);

    // Test cases for lighthouse
    const testCases = {
        frontPage: async () => `${APP_URL}`,
        loginPage: async () => `${APP_URL}/account/login`,
        listingPage: async () => `${APP_URL}/?order=name-asc&p=2`,
        productDetailPage: async () => detailUrl,
        categoryPage: async () => navUrl,
        emptyCartPage: async () => `${APP_URL}/checkout/cart`,
    };

    // Execute lighthouse tests
    const lighthouseTests = [];

    await iterateAsync(Object.entries(testCases), async ([testName, getTestUrl]) => {
        console.log('MEASURE ', testName);
        const url = await getTestUrl();
        console.log('MEASURE URL', url);
        const result = await lighthouse(url, {
            port: PORT,
            disableStorageReset: true,
            output: 'html',
            formFactor: 'desktop',
            screenEmulation: {
                mobile: false,
                width: 1920,
                height: 1080,
            },
        });

        lighthouseTests.push({
            testName: testName,
            result: result,
        });
    });

    //mobile
    await iterateAsync(Object.entries(testCases), async ([testName, getTestUrl]) => {
        testName += '-mobile';
        console.log('MEASURE ', testName);
        const url = await getTestUrl();
        console.log('MEASURE URL', url);
        const result = await lighthouse(url, {
            port: PORT,
            disableStorageReset: true,
            output: 'html',
            formFactor: 'mobile',
            screenEmulation: {
                mobile: true,
                width: 360,
                height: 800,
            },
        });

        lighthouseTests.push({
            testName: testName,
            result: result,
        });
    });


    // with login
    // Test cases for lighthouse
    const testCasesLoggedIn = {
        loggedInFrontPage: async () => `${APP_URL}`,
        loggedInAccountPage: async () => `${APP_URL}/account`,
        loggedInListingPage: async () => `${APP_URL}/?order=name-asc&p=2`,
        loggedInProductDetailPage: async () => detailUrl,
        loggedInCategoryPage: async () => navUrl,
        loggedInFilledCartPage: async () => `${APP_URL}/checkout/cart`,
        loggedInCheckoutStart: async () => `${APP_URL}/checkout/confirm`,
    };

    await loginAndFixtures(browser);

    await iterateAsync(Object.entries(testCasesLoggedIn), async ([testName, getTestUrl]) => {
        console.log('MEASURE ', testName);
        const url = await getTestUrl();
        console.log('MEASURE URL', url);
        const result = await lighthouse(url, {
            port: PORT,
            disableStorageReset: true,
            output: 'html',
            formFactor: 'desktop',
            screenEmulation: {
                mobile: false,
                width: 1920,
                height: 1080,
            },
        });

        lighthouseTests.push({
            testName: testName,
            result: result,
        });
    });



    // Save the result in files
    lighthouseTests.forEach(({ testName, result }) => {
        fse.outputFileSync(
            path.join(PROJECT_ROOT, `/build/artifacts/lighthouse-storefront-results/${testName}.html`),
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
