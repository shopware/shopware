const loginPage = require('../../page-objects/sw-login.page-object.js');

module.exports = {
    '@tags': ['login-failed', 'login'],
    'make sure the user is not logged in already': (browser) => {
        const page = loginPage(browser);

        browser
            .waitForElementVisible('.sw-dashboard-index__content')
            .clickUserActionMenu('admin');
        page.logout('admin');
        browser
            .clearValue('input[name=sw-field--authStore-username]');
    },
    'view login screen': (browser) => {
        browser
            .waitForElementVisible('.sw-login')
            .assert.urlContains('#/login')
            .assert.containsText('.sw-login__form-headline', 'Log in to your Shopware store');
    },
    'attempt to log in with empty login form': (browser) => {
        const page = loginPage(browser);

        browser
            .waitForElementVisible('.sw-login__login-action')
            .click('.sw-login__login-action');
        page.verifyFailedLogin('Incorrect user credentials.');
    },
    'attempt to log in leaving user name field blank': (browser) => {
        const page = loginPage(browser);
        page.login('', 'XY_123#');
        page.verifyFailedLogin('Incorrect user credentials.');
    },
    'attempt to log in with correct user name, leaving password field blank': (browser) => {
        const page = loginPage(browser);
        page.login('admin', '');
        page.verifyFailedLogin('Incorrect user credentials.');
    },
    'attempt to log in leaving password field blank': (browser) => {
        const page = loginPage(browser);
        page.login('XY_123#', '');
        page.verifyFailedLogin('Incorrect user credentials.');
    },
    'attempt to log in with correct password leaving user name field blank': (browser) => {
        const page = loginPage(browser);
        page.login('', 'shopware');
        page.verifyFailedLogin('Incorrect user credentials.');
    },
    'attempt to open the Administration without logging in': (browser) => {
        browser
            .url(`${browser.launch_url}#/sw/dashboard/index`)
            .waitForElementVisible('.sw-login');
    },
    'attempt to log in using invalid credentials': (browser) => {
        const page = loginPage(browser);
        page.login('fakeAdmin', 'shopware');
        page.verifyFailedLogin('Incorrect user credentials.');
    },
    'attempt to log in using invalid credentials including special characters ': (browser) => {
        const page = loginPage(browser);
        page.login('XY_123#', 'X3*SWAGy76p1');
        page.verifyFailedLogin('Incorrect user credentials.');
    },
    'attempt to log in using invalid code credentials': (browser) => {
        const page = loginPage(browser);
        page.login(`<img src="${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png" alt="Some image" height="42" width="42">`,`<img src="${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png" alt="Some image" height="42" width="42">`);
        page.verifyFailedLogin('Incorrect user credentials.');
    },
    after: (browser) => {
        browser.end();
    }
};
