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
            .openMainMenuEntry({
                targetPath: '#/sw/customer/index',
                mainMenuId: 'sw-customer'
            })
            .expect.element(page.elements.smartBarAmount).to.have.text.that.equals('(1)');

        browser
            .clickContextMenuItem('.sw-customer-list__view-action', page.elements.contextMenuButton, `${page.elements.gridRow}--0`)
            .expect.element(`${page.elements.customerMetaData}-customer-name`).to.have.text.that.equals('Mr Pep Eroni');
    },
    'open edit mode': (browser) => {
        browser
            .click('.sw-customer-detail__open-edit-mode-action')
            .waitForElementVisible('input[name=sw-field--customer-salutation]');
    },
    'change customer data and exit edit mode': (browser) => {
        const page = customerPage(browser);

        browser
            .fillField('input[name=sw-field--customer-firstName]', 'Cran', true)
            .fillField('input[name=sw-field--customer-lastName]', 'Berry', true)
            .fillField('input[name=sw-field--customer-email]', 'test-again-and-again@example.com', true)
            .fillSelectField('select[name=sw-field--customer-defaultPaymentMethodId]', 'Direct Debit')
            .tickCheckbox('input[name=sw-field--customer-active]', false)
            .click(page.elements.customerSaveAction)
            .checkNotification('Customer "Mr Cran Berry" has been saved successfully.')
            .waitForElementVisible('.sw-customer-detail__open-edit-mode-action');
    },
    'verify changed customer data': (browser) => {
        const page = customerPage(browser);

        browser
            .assert.containsText(`${page.elements.customerMetaData}-customer-name`, 'Mr Cran Berry')
            .assert.containsText('.sw-customer-card-email-link', 'test-again-and-again@example.com')
            .assert.containsText('.sw-customer-base__label-default-payment-method', 'Direct Debit')
            .assert.containsText('.sw-customer-base__label-is-active', 'Inactive');
    },
    after: (browser) => {
        browser.end();
    }
};
