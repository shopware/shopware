const customerPage = require('administration/page-objects/module/sw-customer.page-object.js');

module.exports = {
    '@tags': ['customer-create', 'customer', 'create'],
    'open customer listing': (browser) => {
        const page = customerPage(browser);

        browser
            .openMainMenuEntry('#/sw/customer/index', 'Customers')
            .waitForElementVisible('.smart-bar__actions')
            .waitForElementVisible(page.elements.smartBarAmount)
            .assert.containsText(page.elements.smartBarAmount, '(0)');
    },
    'create a customer, fill basic data': (browser) => {
        const page = customerPage(browser);

        browser
            .click('a[href="#/sw/customer/create"]')
            .waitForElementVisible('.sw-customer-base-form')
            .assert.urlContains('#/sw/customer/create')
            .assert.containsText(page.elements.cardTitle, 'Account')
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
            .fillField('input[name=sw-field--customer-customerNumber]', '1234321');
    },
    'add customer address': (browser) => {
        const page = customerPage(browser);

        browser
            .assert.urlContains('#/sw/customer/create')
            .assert.containsText(page.elements.cardTitle, 'Account')
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
            .waitForElementPresent(page.elements.customerSaveAction)
            .click(page.elements.customerSaveAction)
            .checkNotification('Customer "Mr Pep Eroni" has been saved successfully.')
            .waitForElementNotPresent('.sw-card__content .sw-customer-base-form .sw-loader')
            .waitForElementNotPresent('.sw-card__content .sw-customer-address-form .sw-loader')
            .waitForElementVisible(page.elements.customerMetaData)
            .assert.containsText(`${page.elements.customerMetaData}-user-name`, 'Mr Pep Eroni')
            .assert.containsText(`${page.elements.customerMetaData}-item`, 'test@example.com')
            .waitForElementVisible('.sw-description-list')
            .assert.containsText('.sw-description-list', '1234321')
            .assert.containsText('.sw-description-list', 'Standard customer group')
            .waitForElementVisible('.sw-card-section--divider-right .sw-address__line span')
            .assert.containsText('.sw-card-section--divider-right .sw-address__line span', '48624');
    },
    'go back to listing und verify data there': (browser) => {
        const page = customerPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .waitForElementVisible('.sw-customer-list__content')
            .fillGlobalSearchField('Pep Eroni')
            .refresh()
            .waitForElementVisible(page.elements.smartBarAmount)
            .assert.containsText(page.elements.smartBarAmount, '(1)')
            .waitForElementPresent(page.elements.columnName)
            .assert.containsText(page.elements.columnName, 'Pep Eroni');
    },
    after: (browser) => {
        browser.end();
    }
};
