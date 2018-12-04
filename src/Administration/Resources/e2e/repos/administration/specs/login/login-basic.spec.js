const loginPage = require('../../page-objects/sw-login.page-object.js');

module.exports = {
    '@tags': ['login-basic', 'login'],
    'make sure the user is not logged in already': (browser) => {
        const page = loginPage(browser);
        browser
            .waitForElementVisible('.sw-dashboard-index__content');
        page.logout('admin');
    },
    'view login screen': (browser) => {
        browser
            .waitForElementVisible('.sw-login')
            .assert.urlContains('#/login')
            .assert.containsText('.sw-login__form-headline', 'Log in to your Shopware store');
    },
    'log in admin user': (browser) => {
        const page = loginPage(browser);
        page.login('admin', 'shopware');
    },
    'verify login': (browser) => {
        const page = loginPage(browser);
        page.verifyLogin('admin');
    },
    after: (browser) => {
        browser.end();
    }
};
