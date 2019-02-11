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
            .openMainMenuEntry({
                mainMenuPath: '#/sw/customer/index',
                menuTitle: 'Customers',
                index: 3
            })
            .waitForElementVisible('.smart-bar__actions a')
            .waitForElementVisible(page.elements.smartBarAmount)
            .assert.containsText(page.elements.smartBarAmount, '(1)');
    },
    'edit customer email via inline editing and verify edits': (browser) => {
        const page = customerPage(browser);

        browser
            .waitForElementVisible(`${page.elements.gridRow}--0`)
            .moveToElement(`${page.elements.gridRow}--0`, 5, 5).doubleClick()
            .waitForElementPresent('.is--inline-editing ')
            .fillField(`${page.elements.gridRow}--0 input[name=sw-field--item-firstName]`, 'Meghan', true)
            .fillField(`${page.elements.gridRow}--0 input[name=sw-field--item-lastName]`, 'Markle', true)
            .waitForElementVisible(`${page.elements.gridRow}--0 ${page.elements.gridRowInlineEdit}`)
            .click(`${page.elements.gridRow}--0 ${page.elements.gridRowInlineEdit}`)
            .waitForElementNotPresent('.is--inline-editing')
            .refresh()
            .waitForElementVisible(`.sw-grid-row ${page.elements.contextMenuButton}`)
            .assert.containsText(page.elements.columnName, 'Meghan Markle');
    },
    after: (browser) => {
        browser.end();
    }
};