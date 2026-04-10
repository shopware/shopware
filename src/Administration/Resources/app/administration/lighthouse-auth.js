/**
 * Lighthouse CI Puppeteer Authentication Script
 *
 * This script handles authentication for the Administration panel before
 * Lighthouse runs its performance measurements. It logs in with the default
 * admin credentials and ensures the session is preserved for the test run.
 *
 * @see https://github.com/GoogleChrome/lighthouse-ci/blob/main/docs/configuration.md#puppeteerscript
 * @sw-package framework
 */

/**
 * @param {import('puppeteer').Browser} browser
 * @param {{ url: string }} context
 */
module.exports = async (browser, context) => {
    const page = await browser.newPage();

    // Navigate to page (wait for document; SPA + /oauth/sso/config load afterwards)
    await page.goto(`${context.url}`, { waitUntil: 'load' });

    // If this selector exists, we are already logged in
    try {
        await page.waitForSelector('.sw-dashboard', { timeout: 3000 });
        return;
    } catch (e) {
        // not logged in, continue to login flow
    }

    // Default login fields live in form.sw-login-login and only mount after loginConfig is loaded.
    // Meteor mt-text-field may not expose name="sw-field--username" on the native input in all builds;
    // use type + form scope (matches one username + one password field).
    const form = 'form.sw-login-login';
    const userField = `${form} input[type="text"]`;
    const passField = `${form} input[type="password"]`;

    await page.waitForSelector(userField, { timeout: 90000 });
    await page.waitForSelector(passField, { timeout: 90000 });

    await page.type(userField, 'admin');
    await page.type(passField, 'shopware');

    // mt-button may merge type from attrs; class is stable on the login CTA
    await page.click(`${form} .sw-login__login-action`);

    // Wait for successful login - the admin should load
    // This indicates the boot process has completed
    await page.waitForSelector('.sw-help-center__button', { timeout: 60000 });

    // Wait additional 3 seconds to ensure everything is fully loaded
    await new Promise((resolve) => {setTimeout(resolve, 3000)});

    // Close the page - Lighthouse will open a new one with the same session
    await page.close();
};

