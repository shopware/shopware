module.exports = {
    'view dashboard': (browser) => {
        browser
            .waitForElementVisible('.sw-dashboard-index__content')
            .end();
    }
};
