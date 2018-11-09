const loginPage = require('../../page-objects/sw-login.page-object.js');

module.exports = {
    '@tags': ['login-failed', 'login'],
    'make sure the user is not logged in already': (browser) => {
        browser.waitForElementVisible('.sw-dashboard-index__content');
        const page = loginPage(browser);
        page.fastLogout('admin');
        browser.clearValue('input[name=sw-field--authStore-username]');
    },
    'view login screen': (browser) => {
        browser
            .waitForElementVisible('.sw-login')
            .assert.urlContains('#/login')
            .assert.containsText('.sw-login__form-headline', 'Log in to your Shopware store');
    },
    'attempt to log in with empty login form': (browser) => {
        browser
            .waitForElementVisible('.sw-login_login-action')
            .click('.sw-login_login-action');
        const page = loginPage(browser);
        page.verifyFailedLogin('Incorrect user credentials.');
    },
    'attempt to log in leaving user name field blank': (browser) => {
        const page = loginPage(browser);
        page.fastLogin('', 'XY_123#');
        page.verifyFailedLogin('Incorrect user credentials.');
    },
    'attempt to log in with correct user name, leaving password field blank': (browser) => {
        const page = loginPage(browser);
        page.fastLogin('admin', '');
        page.verifyFailedLogin('Incorrect user credentials.');
    },
    'attempt to log in leaving password field blank': (browser) => {
        const page = loginPage(browser);
        page.fastLogin('XY_123#', '');
        page.verifyFailedLogin('Incorrect user credentials.');
    },
    'attempt to log in with correct password leaving user name field blank': (browser) => {
        const page = loginPage(browser);
        page.fastLogin('', 'shopware');
        page.verifyFailedLogin('Incorrect user credentials.');
    },
    'attempt to open the Administration without logging in': (browser) => {
        browser
            .url('http://localhost:8000/admin#/sw/dashboard/index')
            .waitForElementVisible('.sw-login');
    },
    'attempt to log in using invalid credentials': (browser) => {
        const page = loginPage(browser);
        page.fastLogin('fakeAdmin', 'shopware');
        page.verifyFailedLogin('Incorrect user credentials.');
    },
    'attempt to log in using invalid credentials including special characters ': (browser) => {
        const page = loginPage(browser);
        page.fastLogin('XY_123#', 'X3*SWAGy76p1');
        page.verifyFailedLogin('Incorrect user credentials.');
    },
    'attempt to log in using invalid code credentials': (browser) => {
        const page = loginPage(browser);
        page.fastLogin('<iframe src="https://i.imgur.com/UXXgSy3.jpg" height="200" width="200" name="iframe">Testbild</iframe>', '<iframe src="https://i.imgur.com/UXXgSy3.jpg" height="200" width="200" name="iframe">Testbild</iframe>');
        page.verifyFailedLogin('Incorrect user credentials.');
    },
    after: (browser) => {
        browser.end();
    }
};
