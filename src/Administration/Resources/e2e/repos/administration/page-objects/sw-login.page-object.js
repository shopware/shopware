class LoginPageObject {
    constructor(browser) {
        this.browser = browser;
        this.elements = {};

        this.elements.usernameField = 'input[name=sw-field--authStore-username]';
        this.elements.passwordField = 'input[name=sw-field--authStore-password]';
        this.elements.submitButton = '.sw-login__login-action';
    }

    login(username, password) {
        this.browser
            .waitForElementVisible('.sw-login__form')
            .assert.urlContains('#/login')
            .fillField(this.elements.usernameField, username, 'input', true)
            .fillField(this.elements.passwordField, password, 'input', true)
            .waitForElementVisible(this.elements.submitButton)
            .click(this.elements.submitButton)
            .waitForElementNotPresent('.sw-loader');
    }

    // Used in order to log in more quickly, e.g. in BeforeScenario
    fastLogin(username, password) {
        this.browser
            .waitForElementVisible('.sw-login__form')
            .fillField(this.elements.usernameField, username)
            .fillField(this.elements.passwordField, password)
            .setValue(this.elements.passwordField, this.browser.Keys.ENTER)
            .waitForElementNotPresent('.sw-loader');
    }

    logout() {
        this.browser
            .waitForElementVisible('.sw-admin-menu__logout-action')
            .click('.sw-admin-menu__logout-action')
            .waitForElementVisible('.sw-login__form-headline')
            .assert.containsText('.sw-login__form-headline', 'Log in to your Shopware store');
    }

    verifyLogin(name) {
        this.browser
            .waitForElementVisible('.sw-admin-menu')
            .clickUserActionMenu(name, false);
    }

    verifyFailedLogin(notificationMessage) {
        this.browser
            .waitForElementVisible('.sw-field--password.has--error')
            .waitForElementVisible('.sw-field--text.has--error')
            .checkNotification(notificationMessage);
    }
}

module.exports = (browser) => {
    return new LoginPageObject(browser);
};
