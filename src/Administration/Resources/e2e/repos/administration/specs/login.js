module.exports = {
    login: (browser, username, password) => {
        username = username || 'admin';
        password = password || 'shopware';

        browser
            .waitForElementVisible('.sw-login')
            .assert.containsText('h2', 'Log in to your Shopware store')
            .setValue('input[name=sw-field--authStore-username]', username)
            .setValue('input[name=sw-field--authStore-password]', [password, browser.Keys.ENTER])
            .waitForElementVisible('.sw-desktop')
            .waitForElementVisible('.sw-admin-menu');

        if (browser.isVisible('.hide-button')) {
            browser.click('.hide-button').waitForElementNotVisible('.hide-button');
        }
    }
};
