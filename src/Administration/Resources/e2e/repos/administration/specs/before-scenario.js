const loginPage = require('../page-objects/sw-login.page-object.js');

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
        browser.element('css selector', '.hide-button', function (result) {
            if (result.status !== -1) {
                browser.click('.hide-button').waitForElementNotVisible('.hide-button');
            }
        });
    }
};