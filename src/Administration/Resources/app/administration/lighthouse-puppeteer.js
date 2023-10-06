/**
 * @package admin
 */

const APP_URL = process.env.APP_URL;

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

    if (usernameInput) {
        await usernameInput.type('admin');
        await passwordInput.type('shopware');
        await loginButton.click();
    }

    await page.waitForNavigation();
    await page.waitForSelector('.sw-dashboard-index__welcome-message');

    await page.close();
}

async function setup(browser) {
    await login(browser);
}

module.exports = setup;

