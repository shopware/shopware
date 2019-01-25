const GeneralPageObject = require('../sw-general.page-object');

class LoginPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);

        this.elements = Object.assign(this.elements, {
            loginForm: '.sw-login__form',
            usernameField: 'input[name=sw-field--authStore-username]',
            passwordField: 'input[name=sw-field--authStore-password]',
            submitButton: '.sw-login__login-action'
        });
    }

    login(username, password) {
        this.browser
            .waitForElementVisible(this.elements.loginForm)
            .assert.urlContains('#/login')
            .fillField(this.elements.usernameField, username, true)
            .fillField(this.elements.passwordField, password)
            .waitForElementVisible(this.elements.submitButton)
            .click(this.elements.submitButton)
            .waitForElementNotPresent(this.elements.loader);
    }

    // Used in order to log in more quickly, e.g. in BeforeScenario
    fastLogin(username, password) {
        this.browser
            .waitForElementVisible(this.elements.loginForm)
            .fillField(this.elements.usernameField, username)
            .fillField(this.elements.passwordField, password)
            .setValue(this.elements.passwordField, this.browser.Keys.ENTER)
            .waitForElementNotPresent(this.elements.loader);
    }

    logout() {
        this.browser
            .waitForElementVisible('.sw-admin-menu__logout-action')
            .click('.sw-admin-menu__logout-action')
            .waitForElementVisible(`${this.elements.loginForm}-headline`)
            .assert.containsText(`${this.elements.loginForm}-headline`, 'Log in to your Shopware store');
    }

    verifyLogin(name) {
        this.browser
            .waitForElementVisible(this.elements.adminMenu)
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
