const accountPage = require('./../../page-objects/account.page-object.js');

module.exports = {
    '@tags': ['account', 'login'],
    before: (browser, done) => {
        global.CustomerFixtureService.setCustomerFixture().then(() => {
            done();
        });
    },
    'find login form': (browser) => {
        const page = accountPage(browser);

        browser
            .click('#accountWidget')
            .waitForElementVisible(page.elements.accountMenu)
            .click('.account-menu-register a')
            .assert.urlContains('/account/login');
    },
    'login as customer': (browser) => {
        const page = accountPage(browser);

        browser
            .waitForElementVisible(page.elements.registerCard)
            .fillField('#loginMail', 'test@example.com')
            .fillField('#loginPassword', 'shopware')
            .click(`.login-submit ${page.elements.primaryButton}`);
    },
    'verify login by seeing /account page': (browser) => {
        const page = accountPage(browser);

        browser
            .waitForElementVisible(page.elements.accountRoot)
            .assert.urlContains('/account')
            .expect.element(page.elements.accountHeadline).to.have.text.that.contains('This is your account dashboard where you can view your recent account activities.');
    },
    after: (browser) => {
        browser.end();
    }
};
