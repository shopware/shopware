const loginPage = require('../page-objects/sw-login.page-object.js');

module.exports = {
    login: (browser, username, password) => {
        username = username || 'admin';
        password = password || 'shopware';

        browser
            .waitForElementVisible('.sw-login')
            .assert.containsText('h2', 'Log in to your Shopware store');
        const page = loginPage(browser);
        page.fastLogin(username, password);
        browser
            .waitForElementVisible('.sw-desktop')
            .waitForElementVisible('.sw-admin-menu');

        if (browser.isVisible('.hide-button')) {
            browser.click('.hide-button').waitForElementNotVisible('.hide-button');
        }
    }
};
