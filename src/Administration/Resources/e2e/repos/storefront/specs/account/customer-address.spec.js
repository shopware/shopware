const accountPage = require('./../../page-objects/account.page-object.js');

module.exports = {
    '@tags': ['account', 'address'],
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
            .click('.account-widget-register a')
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
    'verify first address in overview': (browser) => {
        const page = accountPage(browser);

        browser
            .waitForElementVisible(page.elements.accountRoot)
            .waitForElementVisible(page.elements.overViewBillingAddress)
            .assert.containsText(`${page.elements.overViewBillingAddress} ${page.elements.cardTitle}`, 'Primary billing address')
            .assert.containsText(`${page.elements.overViewBillingAddress} address`, 'Pep Eroni')
            .assert.containsText(`${page.elements.overViewShippingAddress} p`, 'Equal to the billing address');
    },
    'change street in address': (browser) => {
        const page = accountPage(browser);

        browser
            .waitForElementVisible(page.elements.accountRoot)
            .waitForElementVisible(`${page.elements.accountSidebar} .list-group-item:nth-of-type(3)`)
            .click(`${page.elements.accountSidebar} .list-group-item:nth-of-type(3)`)
            .waitForElementVisible(page.elements.addressRoot)
            .click(`.address-box ${page.elements.lightButton}`)
            .waitForElementVisible(page.elements.addressForm)
            .fillField('#addressAddressStreet', '12th Ebbinghoff Street', true)
            .click(`.address-form-submit${page.elements.primaryButton}`)
            .expect.element(page.elements.alertSuccess).to.have.text.that.contains('Address saved successfully');
    },
    'add new address': (browser) => {
        const page = accountPage(browser);

        browser
            .waitForElementVisible(page.elements.addressRoot)
            .click(`.address-item-create ${page.elements.primaryButton}`)
            .expect.element(page.elements.accountHeadline).to.have.text.that.contains('Create a new Address');

        browser
            .waitForElementVisible(page.elements.addressForm)
            .fillSelectField('#addresspersonalSalutation', 'Mr.')
            .fillField('#addresspersonalFirstName', 'Max')
            .fillField('#addresspersonalLastName', 'Monstermann')
            .fillField('#addressAddressStreet', '12th Ebbinghoff Street')
            .fillField('#addressAddressZipcode', '14432')
            .fillField('#addressAddressCity', 'Somewhere')
            .fillSelectField('#addressAddressCountry', 'Germany')
            .click(`.address-form-submit${page.elements.primaryButton}`)
            .expect.element(page.elements.alertSuccess).to.have.text.that.contains('Address saved successfully');
    },
    'check main address to be both shipping and billing address': (browser) => {
        browser
            .expect.element('.default-shipping-address').to.have.text.that.contains('Default shipping address');
        browser.expect.element('.default-billing-address').to.have.text.that.contains('Default billing address');
    },
    'set new address as shipping address': (browser) => {
        const page = accountPage(browser);

        browser
            .waitForElementVisible(`${page.elements.addressBox}:not(.address-front)`)
            .click(
                `${page.elements.addressBox}:not(.address-front) .address-actions-set-defaults form:nth-of-type(1) .btn`
            )
            .expect.element(page.elements.alertSuccess).to.have.text.that.contains('Changed default address successfully.');
        browser
            .waitForElementNotPresent(`${page.elements.addressBox}:not(.address-front)`)
            .assert.containsText('.default-billing-address', 'Pep Eroni')
            .assert.containsText('.default-shipping-address', 'Max Monstermann');
    },
    'verify addresses in account index': (browser) => {
        const page = accountPage(browser);

        browser
            .waitForElementVisible(`${page.elements.accountSidebar} .list-group-item:nth-of-type(1)`)
            .click(`${page.elements.accountSidebar} .list-group-item:nth-of-type(1)`)
            .waitForElementVisible(page.elements.accountRoot)
            .waitForElementVisible(page.elements.overViewBillingAddress)
            .assert.containsText(`${page.elements.overViewBillingAddress} .card-title`, 'Primary billing address')
            .assert.containsText(`${page.elements.overViewBillingAddress} address`, 'Pep Eroni')
            .assert.containsText(`${page.elements.overViewShippingAddress} address`, 'Max Monstermann');
    },
    after: (browser) => {
        browser.end();
    }
};
