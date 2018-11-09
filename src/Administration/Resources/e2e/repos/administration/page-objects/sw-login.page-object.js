class LoginPageObject {
    constructor(browser) {
        this.browser = browser;
        this.elements = {};
    }

    fastLogin(username, password) {
        this.browser
            .waitForElementVisible('.sw-login__form')
            .assert.urlContains('#/login')
            .fillField('input[name=sw-field--authStore-username]', username)
            .fillField('input[name=sw-field--authStore-password]', password)
            .waitForElementVisible('.sw-login_login-action')
            .click('.sw-login_login-action');
    }

    fastLogout(username) {
        this.browser
            .useUserActionMenu(username)
            .waitForElementVisible('.sw-admin-menu__logout-action')
            .click('.sw-admin-menu__logout-action')
            .waitForElementVisible('.sw-login__form-headline')
            .assert.containsText('.sw-login__form-headline', 'Log in to your Shopware store');
    }

    verifyFailedLogin(notificationMessage) {
        this.browser
            .waitForElementVisible('.sw-field--password.has--error')
            .waitForElementVisible('.sw-field--text.has--error')
            .checkNotificationMessage(notificationMessage);
    }
}

module.exports = (browser) => {
    return new LoginPageObject(browser);
};
