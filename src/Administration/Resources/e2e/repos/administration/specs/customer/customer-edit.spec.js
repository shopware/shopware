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
                mainMenuPath: '#/sw/customer/index',
                menuTitle: 'Customers',
                index: 3
            })
            .expect.element(page.elements.smartBarAmount).to.have.text.that.equals('(1)');

        browser
            .clickContextMenuItem('.sw-customer-list__view-action', page.elements.contextMenuButton, `${page.elements.gridRow}--0`)
            .expect.element(`${page.elements.customerMetaData}-user-name`).to.have.text.that.equals('Mr Pep Eroni');
    },
    'change customer email': (browser) => {
        const page = customerPage(browser);

        browser
            .waitForElementPresent('.sw-button--small .sw-button__content .icon--small-pencil')
            .click('.sw-button--small .sw-button__content .icon--small-pencil')
            .waitForElementPresent(page.elements.customerForm)
            .fillField('input[name=sw-field--customer-email]', 'test-again-and-again@example.com', true)
            .waitForElementPresent(page.elements.customerSaveAction)
            .click(page.elements.customerSaveAction)
            .waitForElementNotPresent(`.sw-card__content .sw-customer-base-form ${page.elements.loader}`)
            .waitForElementNotPresent(`.sw-card__content .sw-customer-address-form ${page.elements.loader}`)
            .checkNotification('Customer "Mr Pep Eroni" has been saved successfully.')
            .expect.element(`${page.elements.customerMetaData}-item`).to.have.text.that.contains('test-again-and-again@example.com');
    },
    after: (browser) => {
        browser.end();
    }
};