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
    'edit customer first name and last name via inline editing and verify edits': (browser) => {
        const page = customerPage(browser);

        browser
            .waitForElementVisible(`${page.elements.dataGridRow}--0`)
            .moveToElement(`${page.elements.dataGridRow}--0`, 5, 5).doubleClick()
            .waitForElementPresent('.is--inline-edit ')
            .fillField(`${page.elements.dataGridRow}--0 .sw-customer-list__inline-edit-first-name input`, 'Meghan', true)
            .fillField(`${page.elements.dataGridRow}--0 .sw-customer-list__inline-edit-last-name input`, 'Markle', true)
            .click(`${page.elements.dataGridRow}--0 ${page.elements.dataGridInlineEditSave}`)
            .waitForElementNotPresent('.is--inline-edit')
            .refresh()
            .expect.element(page.elements.columnName).to.have.text.that.equals('Meghan Markle');
    }
};
