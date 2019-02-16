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
                targetPath: '#/sw/customer/index',
                mainMenuId: 'sw-customer'
            })
            .expect.element(page.elements.smartBarAmount).to.have.text.that.equals('(1)');
    },
    'edit customer email via inline editing and verify edits': (browser) => {
        const page = customerPage(browser);

        browser
            .waitForElementVisible(`${page.elements.gridRow}--0`)
            .moveToElement(`${page.elements.gridRow}--0`, 5, 5).doubleClick()
            .waitForElementPresent('.is--inline-editing ')
            .fillField(`${page.elements.gridRow}--0 input[name=sw-field--item-firstName]`, 'Meghan', true)
            .fillField(`${page.elements.gridRow}--0 input[name=sw-field--item-lastName]`, 'Markle', true)
            .click(`${page.elements.gridRow}--0 ${page.elements.gridRowInlineEdit}`)
            .waitForElementNotPresent('.is--inline-editing')
            .refresh()
            .expect.element(page.elements.columnName).to.have.text.that.equals('Meghan Markle');
    },
    after: (browser) => {
        browser.end();
    }
};