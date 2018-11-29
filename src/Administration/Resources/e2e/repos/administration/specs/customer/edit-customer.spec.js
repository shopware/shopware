module.exports = {
    '@tags': ['customer-edit', 'customer', 'edit'],
    'open customer listing': (browser) => {
        browser
            .openMainMenuEntry('#/sw/customer/index', 'Customers')
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
            .fillField('input[name=sw-field--customer-salutation]', 'Mr')
            .fillField('input[name=sw-field--customer-firstName]', 'Pep')
            .fillField('input[name=sw-field--customer-lastName]', 'Eroni')
            .fillField('input[name=sw-field--customer-email]', 'test-again@example.com')
            .fillSelectField('select[name=sw-field--customer-groupId]', 'Standard customer group')
            .fillSelectField('select[name=sw-field--customer-salesChannelId]', 'Storefront API')
            .fillSelectField('select[name=sw-field--customer-defaultPaymentMethodId]', 'Invoice')
            .fillField('input[name=sw-field--customer-customerNumber]', '1234321-edit');
    },
    'add customer address': (browser) => {
        browser
            .assert.urlContains('#/sw/customer/create')
            .assert.containsText('.sw-card__title', 'Account')
            .fillField('input[name=sw-field--address-salutation]', 'Mr')
            .fillField('input[name=sw-field--address-firstName]', 'Pep')
            .fillField('input[name=sw-field--address-lastName]', 'Eroni')
            .fillField('input[name=sw-field--address-street]', 'Ebbinghoff 10')
            .fillField('input[name=sw-field--address-zipcode]', '48624')
            .fillField('input[name=sw-field--address-city]', 'Schöppingen')
            .fillSelectField('select[name="sw-field--address-countryId"]', 'Germany');
    },
    'save and verify new customer': (browser) => {
        browser
            .click('.smart-bar__actions button.sw-button--primary')
            .waitForElementNotPresent('.sw-card__content .sw-customer-base-form .sw-loader')
            .waitForElementNotPresent('.sw-card__content .sw-customer-address-form .sw-loader')
            .waitForElementVisible('.sw-user-card__metadata')
            .assert.containsText('.sw-user-card__metadata-user-name', 'Mr Pep Eroni');
    },
    'change customer email': (browser) => {
        browser
            .waitForElementPresent('.sw-button--small .sw-button__content .icon--small-pencil')
            .click('.sw-button--small .sw-button__content .icon--small-pencil')
            .waitForElementPresent('.sw-customer-base-form')
            .clearValue('input[name=sw-field--customer-email]')
            .setValue('input[name=sw-field--customer-email]', 'test-again-and-again@example.com')
            .waitForElementPresent('.smart-bar__actions button.sw-button--primary')
            .click('.smart-bar__actions button.sw-button--primary')
            .waitForElementNotPresent('.sw-card__content .sw-customer-base-form .sw-loader')
            .waitForElementNotPresent('.sw-card__content .sw-customer-address-form .sw-loader')
            .waitForElementVisible('.sw-user-card__metadata')
            .assert.containsText('.sw-user-card__metadata-item', 'test-again-and-again@example.com');
    },
    'go back to listing': (browser) => {
        browser
            .click('a.smart-bar__back-btn')
            .waitForElementVisible('.sw-customer-list__content')
            .fillGlobalSearchField('Pep Eroni')
            .waitForElementVisible('.sw-page__smart-bar-amount')
            .assert.containsText('.sw-page__smart-bar-amount', '(1)');
    },
    'delete customer and verify deletion': (browser) => {
        browser
            .waitForElementPresent('.sw-customer-list__column-customer-name')
            .assert.containsText('.sw-customer-list__column-customer-name', 'Pep Eroni')
            .clickContextMenuItem('.sw-context-menu-item--danger', '.sw-context-button__button','.sw-grid-row:first-child')
            .waitForElementVisible('.sw-modal')
            .assert.containsText('.sw-modal .sw-customer-list__confirm-delete-text', 'Are you sure you want to delete the customer "Pep Eroni"?')
            .click('.sw-modal__footer button.sw-button--primary')
            .waitForElementNotPresent('.sw-customer-list__column-customer-name')
            .waitForElementNotPresent('.sw-modal')
            .waitForElementPresent('.sw-empty-state__title')
            .assert.containsText('.sw-page__smart-bar-amount', '(0)')
            .end();
    }
};