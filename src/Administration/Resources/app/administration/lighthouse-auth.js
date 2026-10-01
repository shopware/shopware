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

    // Navigate to page
    await page.goto(`${context.url}`);

    // If this selector exists, we are already logged in
    try {
        await page.waitForSelector('.sw-dashboard', { timeout: 3000 });
        return;
    } catch (e) {
        // not logged in, continue to login flow
    }

    // Wait for the login form to be available
    await page.waitForSelector('input#sw-field--username', { timeout: 30000 });

    // Fill in the login credentials (default Shopware admin credentials)
    await page.type('input#sw-field--username', 'admin');
    await page.type('input#sw-field--password', 'shopware');

    // Click the login button
    await page.click('button[type="submit"]');

    // Wait for successful login - the admin should load
    // This indicates the boot process has completed
    await page.waitForSelector('.sw-help-center__button', { timeout: 60000 });

    // Wait additional 3 seconds to ensure everything is fully loaded
    await new Promise((resolve) => {setTimeout(resolve, 3000)});

    // Close the page - Lighthouse will open a new one with the same session
    await page.close();
};

