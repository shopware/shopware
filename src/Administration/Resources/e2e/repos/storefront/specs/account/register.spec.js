module.exports = {
    '@tags': ['account', 'register'],
    'find registration form': (browser) => {
        browser
            .click('#accountWidget')
            .waitForElementVisible('.js-account-widget-dropdown')
            .click('.account-widget-register a')
            .assert.urlContains('/account/login');
    },
    'fill registration form': (browser) => {
        browser
            .waitForElementVisible('.register-card')
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
            .click('.register-submit .btn-primary');
    },
    'verify registration by seeing /account page': (browser) => {
        browser
            .waitForElementVisible('.account')
            .assert.urlContains('/account')
            .expect.element('.account-welcome').to.have.text.that.contains('Welcome, Prof. Dr. John Doe');
    },
    after: (browser) => {
        browser.end();
    }
};
