const GeneralPageObject = require('../sw-general.page-object');

class LoginPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);

        this.elements = {
            ...this.elements,
            ...{
                loginForm: '.sw-login__content',
                usernameField: 'input[name=sw-field--username]',
                passwordField: 'input[name=sw-field--password]',
                submitButton: '.sw-login__login-action'
            }
        };
    }

    login(username, password) {
        this.browser
            .waitForElementVisible(this.elements.loginForm)
            .assert.urlContains('#/login')
            .fillField(this.elements.usernameField, username, true)
            .fillField(this.elements.passwordField, password)
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
            .click('.sw-admin-menu__logout-action')
            .expect.element(`${this.elements.loginForm}-headline`).text.that.equals('Log in to your Shopware store');
    }

    verifyLogin(name) {
        this.browser
            .waitForElementVisible(this.elements.adminMenu)
            .clickUserActionMenu(name, false);
    }

    verifyFailedLogin(notificationMessage) {
        this.browser
            .checkNotification(notificationMessage);
    }
}

module.exports = (browser) => {
    return new LoginPageObject(browser);
};
