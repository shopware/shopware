module.exports = {
    '@tags': ['account', 'address'],
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
    'change street in address': (browser) => {
        browser
            .waitForElementVisible('.account')
            .waitForElementVisible('.account-sidebar .list-group-item:nth-of-type(3)')
            .click('.account-sidebar .list-group-item:nth-of-type(3)')
            .waitForElementVisible('.account-address')
            .click('.address-item-card .btn-light')
            .waitForElementVisible('.account-address-form')
            .fillField('#addressAddressStreet', '12th Ebbinghoff Street', true)
            .click('.address-form-submit.btn-primary')
            .expect.element('.alert-success').to.have.text.that.contains('Address saved successfully');
    },
    'add new address': (browser) => {
        browser
            .waitForElementVisible('.account-address')
            .click('.address-item-create .btn-primary')
            .expect.element('.account-welcome').to.have.text.that.contains('Create a new Address');

        browser
            .waitForElementVisible('.account-address-form')
            .fillSelectField('#addresspersonalSalutation', 'Mr.')
            .fillField('#addresspersonalFirstName', 'Max')
            .fillField('#addresspersonalLastName', 'Monstermann')
            .fillField('#addressAddressStreet', '12th Ebbinghoff Street')
            .fillField('#addressAddressZipcode', '14432')
            .fillField('#addressAddressCity', 'Somewhere')
            .fillSelectField('#addressAddressCountry', 'Germany')
            .click('.address-form-submit.btn-primary')
            .waitForElementVisible('.address-box:nth-of-type(1)')
            .expect.element('.alert-success').to.have.text.that.contains('Address saved successfully');
    },
    'check main address to be both shipping and billing address': (browser) => {
        browser
            .expect.element('.address-front').to.have.text.that.contains('Default shipping address');
        browser.expect.element('.address-front').to.have.text.that.contains('Default billing address');
    },
    'set new address as shipping address': (browser) => {
        browser
            .waitForElementVisible('.address-box:not(.address-front)')
            .click(
                '.address-box:not(.address-front) .address-actions-set-defaults form:nth-of-type(1) .btn'
            )
            .expect.element('.alert-success').to.have.text.that.contains('Changed default address successfully.');
        browser.waitForElementNotPresent('.address-box:not(.address-front)');
    },
    after: (browser) => {
        browser.end();
    }
};
