module.exports = {
    '@tags': ['dashboard'],
    'view dashboard': (browser) => {
        browser
            .waitForElementVisible('.sw-dashboard-index__content')
            .end();
    }
};
