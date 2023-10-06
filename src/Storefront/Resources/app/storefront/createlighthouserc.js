/**
 * @package storefront
 */

/* eslint-disable no-console */
const fs = require('fs');
const puppeteer = require('puppeteer');
const fse = require("fs-extra");
const path = require('path');

// just testing
const APP_URL = process.env.APP_URL;
const PROJECT_ROOT = process.env.PROJECT_ROOT;
const DD_API_KEY = process.env.DD_API_KEY;
const LH_PORT = process.env.LH_PORT ?? 8041;
const LH_URL = process.env.LH_URL;
const LH_TOKEN = process.env.LH_TOKEN;

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
      'WARNING: The environment variable "DD_API_KEY" have to defined. ' +
      'Otherwise it can\'t send metrics to datadog.');
}

async function getDetail(browser) {
    console.log('GET DETAIL URL');
    const page = await browser.newPage();

    await page.setViewport({ width: 1920, height: 1080 });

    await page.goto(`${APP_URL}`);

    const detailHref = await page.$eval('.product-action a.btn', el => el.href)

    await page.goto(detailHref);

    await page.waitForSelector('meta[itemprop="productID"]');
    await page.$('.buy-widget-container .btn-buy');

    const productUrl = await page.$eval('meta[property="product:product_link"]', el => el.content);

    await page.close();

    return productUrl;
}


async function getNavUrl(browser) {
    console.log('GET NAV URL');
    const page = await browser.newPage();

    await page.setViewport({ width: 1920, height: 1080 });

    await page.goto(`${APP_URL}`);
    await page.waitForSelector('a.main-navigation-link');

    const secondNavLinkUrl = await page.$eval('a.main-navigation-link:nth-of-type(2n)', el => el.href);

    await page.close();

    return secondNavLinkUrl;
}

async function main () {
    const browser = await puppeteer.launch({
        args: [
            `--remote-debugging-port=${LH_PORT}`,
            '--no-sandbox',
            '--disable-setuid-sandbox',
        ],
        // For debugging uncomment next line:
        // headless: false,
    });

    const detailUrl = await getDetail(browser);
    const navUrl = await getNavUrl(browser);

    // Test cases for lighthouse
    testCases = {
        frontpage: `${APP_URL}/`,
        loginPage: `${APP_URL}/account/login`,
        listingPage: `${APP_URL}/?order=name-asc&p=2`,
        productDetailPage: detailUrl,
        categoryPage: navUrl,
        emtpyCartPage: `${APP_URL}/checkout/cart`,
    };

    const testCasesMobile = {
        frontpage_mobile: `${APP_URL}/?mobile`,
        loginPage_mobile: `${APP_URL}/account/login?mobile`,
        listingPage_mobile: `${APP_URL}/?order=name-asc&p=2&?mobile`,
        productDetailPage_mobile: `${detailUrl}?mobile`,
        categoryPage_mobile: `${navUrl}?mobile`,
        emtpyCartPage_mobile: `${APP_URL}/checkout/cart?mobile`,
    };

    // with login
    // Test cases for lighthouse
    const testCasesLoggedIn = {
        loggedInFrontPage: `${APP_URL}?loggedIn`,
        loggedInAccountPage: `${APP_URL}/account?loggedIn`,
        loggedInListingPage: `${APP_URL}/?order=name-asc&p=2&loggedIn`,
        loggedInProductDetailPage: `${detailUrl}?loggedIn`,
        loggedInCategoryPage: `${navUrl}?loggedIn`,
        loggedInFilledCartPage: `${APP_URL}/checkout/cart?loggedIn`,
        loggedInCheckoutStart: `${APP_URL}/checkout/confirm?loggedIn`,
    };

    const testCasesLoggedInMobile = {
        loggedInFrontPage_mobile: `${APP_URL}?loggedIn&mobile`,
        loggedInAccountPage_mobile: `${APP_URL}/account?loggedIn&mobile`,
        loggedInListingPage_mobile: `${APP_URL}/?order=name-asc&p=2&loggedIn&mobile`,
        loggedInProductDetailPage_mobile: `${detailUrl}?loggedIn&mobile`,
        loggedInCategoryPage_mobile: `${navUrl}?loggedIn&mobile`,
        loggedInFilledCartPage_mobile: `${APP_URL}/checkout/cart?loggedIn&mobile`,
        loggedInCheckoutStart_mobile: `${APP_URL}/checkout/confirm?loggedIn&mobile`,
    };

    // Close browser when all tests are finished
    await browser.close();

    return {
        'urlMap': {
            ...testCases, ...testCasesMobile, ...testCasesLoggedIn, ...testCasesLoggedInMobile,
        },
        'notLoggedIn': {
            ci: {
                collect: {
                    url: Object.values(testCases),
                    numberOfRuns: 4,
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
        'notLoggedInMobile': {
            ci: {
                collect: {
                    url: Object.values(testCasesMobile),
                    numberOfRuns: 4,
                    settings: {
                        port: LH_PORT,
                        chromeFlags: '--no-sandbox',
                        disableStorageReset: true,
                        output: 'html',
                        formFactor: 'mobile',
                        screenEmulation: {
                            mobile: true,
                            width: 300,
                            height: 800,
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
        'loggedIn': {
            ci: {
                collect: {
                    url: Object.values(testCasesLoggedIn),
                    numberOfRuns: 4,
                    puppeteerScript: './lighthouse-puppeteer.js',
                    puppeteerLaunchOptions: {args: ['--allow-no-sandbox-job', '--allow-sandbox-debugging', '--no-sandbox', '--disable-gpu', '--disable-gpu-sandbox', '--display']},
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
        'loggedInMobile': {
            ci: {
                collect: {
                    url: Object.values(testCasesLoggedInMobile),
                    numberOfRuns: 4,
                    puppeteerScript: './lighthouse-puppeteer.js',
                    puppeteerLaunchOptions: {args: ['--allow-no-sandbox-job', '--allow-sandbox-debugging', '--no-sandbox', '--disable-gpu', '--disable-gpu-sandbox', '--display']},
                    settings: {
                        port: LH_PORT,
                        chromeFlags: '--no-sandbox',
                        disableStorageReset: true,
                        output: 'html',
                        formFactor: 'mobile',
                        screenEmulation: {
                            mobile: true,
                            width: 300,
                            height: 800,
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
    fse.mkdirpSync(path.join(PROJECT_ROOT, '/build/artifacts/lighthouse-storefront-config/'));
    fs.writeFile(path.join(PROJECT_ROOT, '/build/artifacts/lighthouse-storefront-config/lighthousercNotLoggedIn.json'), JSON.stringify(config.notLoggedIn), err => {
        if (err) {
            console.error(err);
        }
        // file written successfully
    });
    fs.writeFile(path.join(PROJECT_ROOT, '/build/artifacts/lighthouse-storefront-config/lighthousercLoggedIn.json'), JSON.stringify(config.loggedIn), err => {
        if (err) {
            console.error(err);
        }
        // file written successfully
    });
    fs.writeFile(path.join(PROJECT_ROOT, '/build/artifacts/lighthouse-storefront-config/lighthousercNotLoggedInMobile.json'), JSON.stringify(config.notLoggedInMobile), err => {
        if (err) {
            console.error(err);
        }
        // file written successfully
    });
    fs.writeFile(path.join(PROJECT_ROOT, '/build/artifacts/lighthouse-storefront-config/lighthousercLoggedInMobile.json'), JSON.stringify(config.loggedInMobile), err => {
        if (err) {
            console.error(err);
        }
        // file written successfully
    });
    fs.writeFile(path.join(PROJECT_ROOT, '/build/artifacts/lighthouse-storefront-config/urlmap.json'), JSON.stringify(config.urlMap), err => {
        if (err) {
            console.error(err);
        }
        // file written successfully
    });
    console.log('wrote');
});


