const loginPage = require('../page-objects/module/sw-login.page-object.js');

const symfonyToolbarSelector = '.sf-toolbar';
const symfonyToolbarButtonSelector = '.sf-toolbar .hide-button';

module.exports = {
    /**
     * @param {Object} browser
     * @param {String} [username='admin']
     * @param {String} [password="shopware"]
     */
    loginIfSessionFailed: (browser, username, password) => {
        const page = loginPage(browser);

        browser.checkIfElementExists(page.elements.loginForm, (result) => {
            browser.waitForElementVisible('#app');
            if (result.value) {
                global.logger.error(`Login check: ${result.value}, which means the session is broken. Trying again.`);
                page.fastLogin(username, password);
            }
        });
    },
    hideToolbarIfVisible: (browser) => {
        browser.checkIfElementExists(symfonyToolbarSelector, (result) => {
            if (result.value) {
                global.logger.error(`Element "${symfonyToolbarButtonSelector}" was detected and will be collapsed.`);
                browser
                    .waitForElementVisible(symfonyToolbarButtonSelector)
                    .click(symfonyToolbarButtonSelector)
                    .waitForElementNotVisible(symfonyToolbarButtonSelector);
            }
        });
    }
};
