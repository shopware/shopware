const accountPage = require('./../../page-objects/account.page-object.js');

module.exports = {
    '@tags': ['account', 'register'],
    'find registration form': (browser) => {
        const page = accountPage(browser);

        browser
            .click('#accountWidget')
            .waitForElementVisible(page.elements.accountMenu)
            .click('.account-widget-register a')
            .assert.urlContains('/account/login');
    },
    'fill registration form': (browser) => {
        const page = accountPage(browser);

        browser
            .waitForElementVisible(page.elements.registerCard)
            .fillSelectField('select[name="salutationId"]', 'Mr.')
            .fillField('input[name="title"]', 'Prof. Dr.')
            .fillField('input[name="firstName"]', 'John')
            .fillField('input[name="lastName"]', 'Doe')
            .fillField('select[name="birthdayDay"]', '4')
            .fillField('select[name="birthdayMonth"]', '8')
            .fillField('select[name="birthdayYear"]', '1917')
            .fillField('.register-form input[name="email"]', `john+${Math.random() * 100}@example.com`)
            .fillField('.register-form input[name="password"]', '1234567890')
            .fillField('input[name="billingAddress[street]"]', '123 Main St')
            .fillField('input[name="billingAddress[zipcode]"]', '9876')
            .fillField('input[name="billingAddress[city]"]', 'Anytown')
            .fillSelectField('select[name="billingAddress[countryId]"]', 'USA')
            .click(`.register-submit ${page.elements.primaryButton}`);
    },
    'verify registration by seeing /account page': (browser) => {
        const page = accountPage(browser);

        browser
            .waitForElementVisible(page.elements.accountRoot)
            .assert.urlContains('/account')
            .expect.element(page.elements.accountHeadline).to.have.text.that.contains('Welcome, Prof. Dr. John Doe');
    },
    after: (browser) => {
        browser.end();
    }
};
