const customerPage = require('administration/page-objects/module/sw-customer.page-object.js');

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
        const page = customerPage(browser);

        browser
            .openMainMenuEntry('#/sw/customer/index', 'Customers')
            .waitForElementVisible('.smart-bar__actions a')
            .waitForElementVisible(page.elements.smartBarAmount)
            .assert.containsText(page.elements.smartBarAmount, '(1)')
            .clickContextMenuItem('.sw-customer-list__view-action', '.sw-context-button__button', '.sw-grid-row:first-child')
            .waitForElementVisible(page.elements.customerMetaData)
            .assert.containsText(`${page.elements.customerMetaData}-user-name`, 'Mr Pep Eroni');
    },
    'change customer email': (browser) => {
        const page = customerPage(browser);

        browser
            .waitForElementPresent('.sw-button--small .sw-button__content .icon--small-pencil')
            .click('.sw-button--small .sw-button__content .icon--small-pencil')
            .waitForElementPresent(page.elements.customerForm)
            .fillField('input[name=sw-field--customer-email]', 'test-again-and-again@example.com', true)
            .waitForElementPresent('.smart-bar__actions button.sw-button--primary')
            .click('.smart-bar__actions button.sw-button--primary')
            .waitForElementNotPresent('.sw-card__content .sw-customer-base-form .sw-loader')
            .waitForElementNotPresent('.sw-card__content .sw-customer-address-form .sw-loader')
            .checkNotification('Customer "Mr Pep Eroni" has been saved successfully.')
            .waitForElementVisible(page.elements.customerMetaData)
            .assert.containsText(`${page.elements.customerMetaData}-item`, 'test-again-and-again@example.com');
    },
    after: (browser) => {
        browser.end();
    }
};