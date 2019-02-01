const customerPage = require('administration/page-objects/module/sw-customer.page-object.js');

module.exports = {
    '@tags': ['customer-delete', 'customer', 'delete'],
    before: (browser, done) => {
        global.CustomerFixtureService.setCustomerFixture({
            email: 'test-again@example.com'
        }).then(() => {
            done();
        });
    },
    'open customer listing': (browser) => {
        const page = customerPage(browser);

        browser
            .openMainMenuEntry('#/sw/customer/index', 'Customers')
            .waitForElementVisible(page.elements.smartBarAmount)
            .assert.containsText(page.elements.smartBarAmount, '(1)');
    },
    'delete customer and verify deletion': (browser) => {
        const page = customerPage(browser);

        browser
            .waitForElementPresent(page.elements.columnName)
            .assert.containsText(page.elements.columnName, 'Pep Eroni')
            .clickContextMenuItem(`${page.elements.contextMenu}-item--danger`, page.elements.contextMenuButton, `${page.elements.gridRow}--0`)
            .waitForElementVisible(page.elements.modal)
            .assert.containsText(`.sw-modal .sw-customer-list__confirm-delete-text`, 'Are you sure you want to delete the customer "Pep Eroni"?')
            .click(`${page.elements.modal}__footer button${page.elements.primaryButton}`)
            .waitForElementNotPresent(page.elements.columnName)
            .waitForElementNotPresent(page.elements.modal)
            .waitForElementPresent(page.elements.emptyState)
            .assert.containsText(page.elements.smartBarAmount, '(0)');
    },
    after: (browser) => {
        browser.end();
    }
};