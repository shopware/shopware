const loginPage = require('../page-objects/module/sw-login.page-object.js');

const symfonyToolbarButtonSelector = '.hide-button';

module.exports = {
    /**
     * @param {Object} browser
     * @param {String} [username='admin']
     * @param {String} [password="shopware"]
     */
    login: (browser, username, password) => {
        const page = loginPage(browser);
        page.fastLogin(username, password);
    },
    hideToolbarIfVisible: (browser) => {
        browser.checkIfElementExists(symfonyToolbarButtonSelector, (result) => {
            if (result.value) {
                global.logger.error(`Element "${symfonyToolbarButtonSelector}" was detected and will be collapsed.`);
                browser.click(symfonyToolbarButtonSelector).waitForElementNotVisible(symfonyToolbarButtonSelector);
            }
        });
    }
};
