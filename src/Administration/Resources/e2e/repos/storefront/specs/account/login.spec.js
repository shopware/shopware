module.exports = {
    '@tags': ['account', 'login'],
    before: (browser, done) => {
        global.CustomerFixtureService.setCustomerFixture().then(() => {
            done();
        });
    },
    'find login form': (browser) => {
        browser
            .click('#accountWidget')
            .waitForElementVisible('.js-account-widget-dropdown')
            .click('.account-widget-register a')
            .assert.urlContains('/account/login');
    },
    'login as customer': (browser) => {
        browser
            .waitForElementVisible('.register-card')
            .fillField('#loginMail', 'test@example.com')
            .fillField('#loginPassword', 'shopware')
            .click('.login-submit .btn-primary');
    },
    'verify login by seeing /account page': (browser) => {
        browser
            .waitForElementVisible('.account')
            .assert.urlContains('/account')
            .expect.element('.account-welcome').to.have.text.that.contains('Welcome, Pep Eroni');
    },
    after: (browser) => {
        browser.end();
    }
};
