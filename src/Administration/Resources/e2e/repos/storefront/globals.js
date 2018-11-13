module.exports = {
    waitForConditionTimeout: 5000,
    asyncHookTimeout: 5000,

    beforeEach: (browser, done) => {
        browser.url(browser.launch_url);

        done();
    }
};