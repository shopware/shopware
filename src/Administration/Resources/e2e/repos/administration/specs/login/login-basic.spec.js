const loginPage = require('../../page-objects/module/sw-login.page-object.js');

module.exports = {
    '@tags': ['login-basic', 'login'],
    'make sure the user is not logged in already': (browser) => {
        const page = loginPage(browser);

        browser
            .waitForElementVisible('.sw-dashboard-index__content')
            .clickUserActionMenu('admin');
        page.logout('admin');
    },
    'view login screen': (browser) => {
        const page = loginPage(browser);

        browser
            .waitForElementVisible('.sw-login')
            .assert.urlContains('#/login')
            .assert.containsText(`${page.elements.loginForm}-headline`, 'Log in to your Shopware store');
    },
    'attempt to log in using invalid credentials': (browser) => {
        const page = loginPage(browser);
        page.login('fakeAdmin', 'shopware');
        page.verifyFailedLogin('Incorrect user credentials.');
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
