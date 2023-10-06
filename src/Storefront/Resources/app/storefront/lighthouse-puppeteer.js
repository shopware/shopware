/**
 * @package storefront
 */

const APP_URL = process.env.APP_URL;

/**
 *
 * @param browser Browser
 * @returns {Promise<void>}
 */
async function loginAndFixtures(browser) {
    console.log('LOGIN');
    const page = await browser.newPage();

    await page.setViewport({ width: 1920, height: 1080 });
    console.log(`${APP_URL}/account/login`);
    await page.goto(`${APP_URL}/account/login`);

    const usernameInput = await page.$('#loginMail');
    const passwordInput = await page.$('#loginPassword');
    const loginButton = await page.$('.login-submit .btn-primary');

    if (usernameInput === null) {
        return;
    }

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

async function setup(browser, context) {
    await loginAndFixtures(browser);
}

module.exports = setup;

