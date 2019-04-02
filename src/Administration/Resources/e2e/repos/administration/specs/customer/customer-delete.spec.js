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
            .openMainMenuEntry({
                targetPath: '#/sw/customer/index',
                mainMenuId: 'sw-customer'
            })
            .expect.element(page.elements.smartBarAmount).to.have.text.that.equals('(1)');
    },
    'delete customer and verify deletion': (browser) => {
        const page = customerPage(browser);

        browser.expect.element(page.elements.columnName).to.have.text.that.equals('Pep Eroni');
        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: `${page.elements.contextMenu}-item--danger`,
                scope: `${page.elements.dataGridRow}--0`
            })
            .expect.element('.sw-modal .sw-customer-list__confirm-delete-text').to.have.text.that.equals('Are you sure you want to delete the customer "Pep Eroni"?');

        browser
            .click(`${page.elements.modal}__footer button${page.elements.primaryButton}`)
            .waitForElementNotPresent(page.elements.modal)
            .waitForElementPresent(page.elements.emptyState)
            .expect.element(page.elements.smartBarAmount).to.have.text.that.equals('(0)');
    }
};
