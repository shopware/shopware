const loginPage = require('./specs/before-scenario.js');

module.exports = {
    waitForConditionTimeout: 30000,
    asyncHookTimeout: 30000,

    beforeEach: (browser, done) => {
        browser.url(browser.launch_url);

        browser.execute(function() {
            // Disable the auto closing of notifications globally.
            Shopware.State.getStore('notification')._defaults.autoClose = false;

            // Return bearer token
            return localStorage.getItem('bearerAuth');
        }, [], (data) => {
            if (!data.value) {
                loginPage.login(browser);
            }

            done();
        });
    }
};
