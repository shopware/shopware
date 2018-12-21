module.exports = {
    '@tags': ['customer-edit', 'customer', 'edit'],
    before: (browser, done) => {
        global.CustomerFixtureService.setCustomerFixture({
            email: 'test-again@example.com'
        }).then(() => {
            done();
        });
    },
    'open customer listing and look for customer to be edited': (browser) => {
        browser
            .openMainMenuEntry('#/sw/customer/index', 'Customers')
            .waitForElementVisible('.smart-bar__actions a')
            .waitForElementVisible('.sw-page__smart-bar-amount')
            .assert.containsText('.sw-page__smart-bar-amount', '(1)')
            .clickContextMenuItem('.sw-customer-list__view-action', '.sw-context-button__button', '.sw-grid-row:first-child')
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
            .checkNotification('Customer "Mr Pep Eroni" has been saved successfully.')
            .waitForElementVisible('.sw-user-card__metadata')
            .assert.containsText('.sw-user-card__metadata-item', 'test-again-and-again@example.com');
    },
    after: (browser) => {
        browser.end();
    }
};