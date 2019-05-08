module.exports = {
    '@tags': ['dashboard', 'overview-open', 'open'],
    'view dashboard': (browser) => {
        browser
            .waitForElementVisible('.sw-dashboard-index__content')
            .expect.element('.sw-dashboard__paypal-icon').to.have.attribute('src').which.contains('/static/img/paypal-official-logo.png');
    }
};
