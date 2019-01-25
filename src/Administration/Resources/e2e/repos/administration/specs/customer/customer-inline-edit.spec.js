const customerPage = require('administration/page-objects/module/sw-customer.page-object.js');

module.exports = {
    '@tags': ['customer-inline-edit', 'customer', 'inline-edit'],
    before: (browser, done) => {
        global.CustomerFixtureService.setCustomerFixture().then(() => {
            done();
        });
    },
    'open customer listing': (browser) => {
        const page = customerPage(browser);

        browser
            .openMainMenuEntry('#/sw/customer/index', 'Customers')
            .waitForElementVisible('.smart-bar__actions a')
            .waitForElementVisible(page.elements.smartBarAmount)
            .assert.containsText(page.elements.smartBarAmount, '(1)');
    },
    'edit customer email via inline editing and verify edits': (browser) => {
        const page = customerPage(browser);

        browser
            .waitForElementVisible(`${page.elements.gridRow} .sw-context-button__button`)
            .moveToElement(`${page.elements.gridRow}:first-child`, 5, 5).doubleClick()
            .waitForElementPresent('.is--inline-editing ')
            .fillField('input[name=sw-field--item-firstName]', 'Meghan', true)
            .fillField('input[name=sw-field--item-lastName]', 'Markle', true)
            .waitForElementVisible(`.sw-grid-row__inline-edit-action`)
            .click(`${page.elements.gridRow}__inline-edit-action`)
            .waitForElementNotPresent('.is--inline-editing ')
            .refresh()
            .waitForElementVisible(`${page.elements.gridRow} .sw-context-button__button`)
            .assert.containsText(page.elements.columnName, 'Meghan Markle')
            .moveToElement(`.sw-grid-row:first-child`, 5, 5).doubleClick()
            .waitForElementVisible(`${page.elements.gridRow}__inline-edit-action`)
            .fillField('.is--inline-editing .sw-field__input input', '007-JB-337826', true)
            .click(`${page.elements.gridRow}__inline-edit-action`)
            .waitForElementNotPresent('.is--inline-editing ')
            .assert.containsText(`.sw-grid-column.sw-grid__cell.sw-grid-column--right`, '007-JB-337826');
    },
    after: (browser) => {
        browser.end();
    }
};