const loginPage = require('../page-objects/sw-login.page-object.js');

module.exports = {
    /**
     * @param {Object} browser
     * @param {String} [username='admin']
     * @param {String} [password="shopware"]
     * @param {Function} [callbackFn=() => {}]
     */
    login: (browser, username, password, callbackFn = () => {}) => {
        const page = loginPage(browser);
        page.fastLogin(username, password);

        browser.element('css selector', '.hide-button', function(result) {
            if (result.status !== -1) {
                browser.click('.hide-button').waitForElementNotVisible('.hide-button');
            }

            callbackFn.call(null);
        });
    }
};