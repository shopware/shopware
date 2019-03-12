const customerPage = require('administration/page-objects/module/sw-customer.page-object.js');

module.exports = {
    '@tags': ['customer-create', 'customer', 'create'],
    'open customer listing': (browser) => {
        const page = customerPage(browser);

        browser
            .openMainMenuEntry({
                targetPath: '#/sw/customer/index',
                mainMenuId: 'sw-customer'
            })
            .expect.element(page.elements.smartBarAmount).to.have.text.that.equals('(0)');
    },
    'create a customer, fill basic data': (browser) => {
        const page = customerPage(browser);

        browser
            .click('a[href="#/sw/customer/create"]')
            .assert.urlContains('#/sw/customer/create')
            .expect.element(`.sw-card:nth-of-type(1) ${page.elements.cardTitle}`).to.have.text.that.equals('Account');

        browser
            .fillField('input[name=sw-field--customer-salutation]', 'Mr')
            .fillField('input[name=sw-field--customer-firstName]', 'Pep')
            .fillField('input[name=sw-field--customer-lastName]', 'Eroni')
            .fillField(page.elements.customerMailInput, 'test@example.com')
            .waitForElementNotPresent('.sw-field--customer-groupId .sw-field__select-load-placeholder')
            .fillSelectField('select[name=sw-field--customer-groupId]', 'Standard customer group')
            .waitForElementNotPresent('.sw-field--customer-salesChannelId .sw-field__select-load-placeholder')
            .fillSelectField('select[name=sw-field--customer-salesChannelId]', 'Storefront API')
            .waitForElementNotPresent('.sw-field--customer-defaultPaymentMethodId .sw-field__select-load-placeholder')
            .fillSelectField('select[name=sw-field--customer-defaultPaymentMethodId]', 'Invoice')
            .fillField('input[name=sw-field--customer-customerNumber]', 'cn-1234321', true);
    },
    'add customer address': (browser) => {
        const page = customerPage(browser);

        browser.expect.element(`.sw-card:nth-of-type(1) ${page.elements.cardTitle}`).to.have.text.that.equals('Account');

        browser
            .fillField('input[name=sw-field--address-salutation]', 'Mr')
            .fillField('input[name=sw-field--address-firstName]', 'Pep')
            .fillField('input[name=sw-field--address-lastName]', 'Eroni')
            .fillField('input[name=sw-field--address-street]', 'Ebbinghoff 10')
            .fillField('input[name=sw-field--address-zipcode]', '48624')
            .fillField('input[name=sw-field--address-city]', 'Schöppingen')
            .waitForElementNotPresent('.sw-field--address-countryId .sw-field__select-load-placeholder')
            .fillSelectField('select[name="sw-field--address-countryId"]', 'Germany');
    },
    'save new customer and verify data': (browser) => {
        const page = customerPage(browser);

        browser
            .click(page.elements.customerSaveAction)
            .checkNotification('Customer "Mr Pep Eroni" has been saved successfully.')
            .waitForElementNotPresent('.sw-card__content .sw-customer-base-form .sw-loader')
            .waitForElementNotPresent('.sw-card__content .sw-customer-address-form .sw-loader')
            .waitForElementVisible(page.elements.customerMetaData)
            .assert.containsText(`${page.elements.customerMetaData}-customer-name`, 'Mr Pep Eroni')
            .assert.containsText('.sw-customer-card-email-link', 'test@example.com')
            .assert.containsText('.sw-customer-base__label-customer-number', 'cn-1234321')
            .assert.containsText('.sw-address__location', '48624');
    },
    'go back to listing und verify data there': (browser) => {
        const page = customerPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .waitForElementVisible('.sw-customer-list__content')
            .fillGlobalSearchField('Pep Eroni')
            .refresh()
            .expect.element(page.elements.smartBarAmount).to.have.text.that.equals('(1)');
        browser.expect.element(page.elements.columnName).to.have.text.that.equals('Pep Eroni');
    },
    after: (browser) => {
        browser.end();
    }
};
