const loginPage = require('../../page-objects/sw-login.page-object.js');

module.exports = {
    '@tags': ['login-basic', 'login'],
    'make sure the user is not logged in already': (browser) => {
        browser
            .waitForElementVisible('.sw-dashboard-index__content');
        const page = loginPage(browser);
        page.fastLogout('admin');
    },
    'view login screen': (browser) => {
        browser
            .waitForElementVisible('.sw-login')
            .assert.urlContains('#/login')
            .assert.containsText('.sw-login__form-headline', 'Log in to your Shopware store');
    },
    'log in admin user': (browser) => {
        const page = loginPage(browser);
        page.fastLogin('admin', 'shopware');
    },
    'verify login': (browser) => {
        browser
            .waitForElementVisible('.sw-desktop')
            .waitForElementVisible('.sw-admin-menu');
    },
    after: (browser) => {
        browser.end();
    }
};
