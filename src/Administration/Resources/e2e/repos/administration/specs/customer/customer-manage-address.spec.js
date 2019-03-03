const customerPage = require('administration/page-objects/module/sw-customer.page-object.js');

module.exports = {
    '@tags': ['customer-manage-addresses', 'customer', 'addresses'],
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
    'open address tab with first address': (browser) => {
        const page = customerPage(browser);

        browser
            .click('.sw-customer-detail__tab-addresses')
            .expect.element(`${page.elements.gridRow}--0`).to.have.text.that.contains('Eroni');
    },
    'ensure that a default shipping and billing address is given and cannot be deleted': (browser) => {
        const page = customerPage(browser);

        browser
            .waitForElementVisible('.icon--default-shopping-cart')
            .expect.element('#defaultShippingAddress-0').to.be.selected;
        browser.expect.element('#defaultBillingAddress-0').to.be.selected;

        browser
            .click(page.elements.contextMenuButton)
            .waitForElementVisible(page.elements.contextMenu)
            .waitForElementVisible(`${page.elements.contextMenu}-item--danger`);
    },
    'activate edit mode': (browser) => {
        browser
            .click('.sw-customer-detail__open-edit-mode-action')
            .waitForElementVisible('.sw-customer-detail__save-action');
    },
    'add second address': (browser) => {
        const page = customerPage(browser);

        browser
            .click('.sw-customer-detail-addresses__add-address-action');

        page.createBasicAddress();

        browser.waitForElementNotPresent(page.elements.modal);
    },
    'swap default billing and shipping address': (browser) => {
        const page = customerPage(browser);

        browser
            .waitForElementVisible('.icon--default-shopping-cart')
            .click(`${page.elements.gridRow}--1 #defaultShippingAddress-0`)
            .click(`${page.elements.gridRow}--0 #defaultBillingAddress-0`)
            .expect.element(`${page.elements.gridRow}--1 #defaultShippingAddress-0`).to.be.selected;
        browser.expect.element(`${page.elements.gridRow}--0 #defaultBillingAddress-0`).to.be.selected;
    },
    'save customer': (browser) => {
        browser
            .click('.sw-customer-detail__save-action')
            .checkNotification('Customer "Mr Pep Eroni" has been saved successfully.');
    },
    'remove address': (browser) => {
        const page = customerPage(browser);

        browser
            .click(`${page.elements.gridRow}--0 #defaultShippingAddress-0`)
            .clickContextMenuItem(`${page.elements.contextMenu}-item--danger`, page.elements.contextMenuButton, `${page.elements.gridRow}--1`)
            .expect.element('.sw-customer-detail-addresses__confirm-delete-text').to.have.text.that.equals('Are you sure you want to delete this address?');

        browser
            .assert.containsText('.sw-address__full-name', 'Mr Harry Potter')
            .click(`${page.elements.modal}__footer button${page.elements.primaryButton}`)
            .waitForElementNotPresent(page.elements.modal)
            .waitForElementNotPresent(`${page.elements.gridRow}--1`);
    },
    after: (browser) => {
        browser.end();
    }
};
