module.exports = {
    login: (browser, username, password) => {
        username = username || 'admin';
        password = password || 'shopware';

        browser.resizeWindow(1920, 1080);
        browser.url(browser.launch_url)
            .waitForElementVisible('.sw-login')
            .assert.containsText('h2', 'Log in to your Shopware store')
            .setValue('input[name=sw-field--authStore-username]', username)
            .setValue('input[name=sw-field--authStore-password]', [password, browser.Keys.ENTER])
            .waitForElementVisible('.sw-desktop')
            .waitForElementVisible('.sw-admin-menu');
    }
};
