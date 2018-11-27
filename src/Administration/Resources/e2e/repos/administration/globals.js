const loginPage = require('./specs/login.js');

module.exports = {
    waitForConditionTimeout: 5000,
    asyncHookTimeout: 5000,

    beforeEach: (browser, done) => {
        console.log(browser.launch_url);
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
