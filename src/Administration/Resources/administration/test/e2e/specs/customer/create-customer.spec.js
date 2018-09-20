module.exports = {
    'open customer listing': (browser) => {
        browser
            .assert.containsText('.sw-admin-menu__navigation-list-item.sw-customer span.collapsible-text', 'Customers')
            .click('a.sw-admin-menu__navigation-link[href="#/sw/customer/index"]')
            .waitForElementVisible('.smart-bar__actions a')
            .waitForElementVisible('.sw-page__smart-bar-amount')
            .assert.containsText('.sw-page__smart-bar-amount', '(0)');
    },
    'create a customer, fill basic data': (browser) => {
        browser
            .click('a[href="#/sw/customer/create"]')
            .waitForElementVisible('.sw-customer-base-form')
            .assert.urlContains('#/sw/customer/create')
            .assert.containsText('.sw-card__title', 'Account')
            .setValue('input[name=sw-field--customer-salutation]', 'Mr')
            .setValue('input[name=sw-field--customer-firstName]', 'Pep')
            .setValue('input[name=sw-field--customer-lastName]', 'Eroni')
            .setValue('input[name=sw-field--customer-email]', 'test@example.com')
            .setValue('select[name=sw-field--customer-groupId]', 'Standard customer group')
            .setValue('select[name=sw-field--customer-salesChannelId]', 'Storefront API')
            .setValue('select[name=sw-field--customer-defaultPaymentMethodId]', 'Invoice')
            .setValue('input[name=sw-field--customer-customerNumber]', '1234321');

    },
    'add customer address': (browser) => {
        browser
            .assert.urlContains('#/sw/customer/create')
            .assert.containsText('.sw-card__title', 'Account')
            .setValue('input[name=sw-field--address-salutation]', 'Mr')
            .setValue('input[name=sw-field--address-firstName]', 'Pep')
            .setValue('input[name=sw-field--address-lastName]', 'Eroni')
            .setValue('input[name=sw-field--address-street]', 'Ebbinghoff 10')
            .setValue('input[name=sw-field--address-zipcode]', '48624')
            .setValue('input[name=sw-field--address-city]', 'Schöppingen')
            .setValue('select[name="sw-field--address-countryId"]', 'Germany');
    },
    'save new customer and verify data': (browser) => {
        browser
            .waitForElementPresent('.smart-bar__actions button.sw-button--primary')
            .click('.smart-bar__actions button.sw-button--primary')
            .waitForElementNotPresent('.sw-card__content .sw-customer-base-form .sw-loader', 5000)
            .waitForElementNotPresent('.sw-card__content .sw-customer-address-form .sw-loader', 5000)
            .waitForElementVisible('.sw-user-card__metadata')
            .assert.containsText('.sw-user-card__metadata-user-name', 'Mr Pep Eroni')
            .assert.containsText('.sw-user-card__metadata-item', 'test@example.com')
            .waitForElementVisible('.sw-description-list')
            .assert.containsText('.sw-description-list', '1234321')
            .assert.containsText('.sw-description-list', 'Standard customer group')
            .waitForElementVisible('.sw-card-section--divider-right .sw-address__line span')
            .assert.containsText('.sw-card-section--divider-right .sw-address__line span', '48624');
    },
    'go back to listing': (browser) => {
        browser
            .click('a.smart-bar__back-btn')
            .waitForElementVisible('.sw-customer-list__content')
            .setValue('input.sw-search-bar__input', ['Pep Eroni', browser.Keys.ENTER])
            .click('.sw-alert button.sw-alert__close')
            .waitForElementNotPresent('.sw-alert__message')
            .waitForElementVisible('.sw-page__smart-bar-amount')
            .assert.containsText('.sw-page__smart-bar-amount', '(1)')
            .end();
    }
};