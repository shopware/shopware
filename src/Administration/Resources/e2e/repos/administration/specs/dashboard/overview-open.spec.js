module.exports = {
    '@tags': ['dashboard','overview-open', 'open'],
    'view dashboard': (browser) => {
        browser
            .waitForElementVisible('.sw-dashboard-index__content');
    },
    after: (browser) => {
        browser.end();
    }
};
