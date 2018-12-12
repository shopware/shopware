module.exports = {
    '@tags': ['overview'],
    'view dashboard': (browser) => {
        browser
            .waitForElementVisible('.sw-dashboard-index__content')
    },
    after: (browser) => {
        browser.end();
    }
};
