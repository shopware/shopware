const loginPage = require('../../page-objects/module/sw-login.page-object.js');

module.exports = {
    '@tags': ['login', 'login-password-recover', 'password'],
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
            .expect.element(`${page.elements.loginForm}-headline`).to.have.text.that.equals('Log in to your Shopware store');
    },
    'choose to recover password': (browser) => {
        const page = loginPage(browser);

        browser
            .click('.sw-login__forgot-password-action')
            .fillField('input[name=sw-field--email]', 'test@example.com')
            .click('.sw-button--primary')
            .waitForElementNotPresent(page.elements.loader)
            .expect.element('.sw-alert__message').to.have.text.that.contains('If you entered a valid email address, you\'ll receive your password recovery mail in a few minutes');
    },
    'return to login screen': (browser) => {
        browser
            .click('.sw-login__back')
            .waitForElementVisible('.sw-login');
    }
};
