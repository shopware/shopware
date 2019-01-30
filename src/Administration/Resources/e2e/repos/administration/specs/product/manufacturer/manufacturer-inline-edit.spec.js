const manufacturerPage = require('administration/page-objects/module/sw-manufacturer.page-object.js');

module.exports = {
    '@tags': ['manufacturer-inline-edit', 'manufacturer', 'inline-edit'],
    before: (browser, done) => {
        global.FixtureService.create('product-manufacturer').then(() => {
            done();
        });
    },
    'navigate to manufacturer module': (browser) => {
        browser
            .openMainMenuEntry('#/sw/product/index', 'Product', '#/sw/manufacturer/index', 'Manufacturer')
            .assert.urlContains('#/sw/manufacturer/index')
            .waitForElementVisible('.sw-button__content');
    },
    'inline edit manufacturer name and website and verify edits': (browser) => {
        const page = manufacturerPage(browser);

        browser
            .waitForElementVisible(`${page.elements.gridRow}--0 > .sw-manufacturer-list_column-manufacturer-description`)
            .moveToElement(`${page.elements.gridRow}--0 > .sw-manufacturer-list_column-manufacturer-description`, 5, 5)
            .doubleClick()
            .waitForElementPresent('.is--inline-editing ')
            .fillField(`${page.elements.gridRow}--0 input[name=sw-field--item-name]`, 'I am Groot', true)
            .waitForElementVisible(page.elements.gridRowInlineEdit)
            .click(page.elements.gridRowInlineEdit)
            .waitForElementNotPresent('.is--inline-editing')
            .waitForElementVisible('.sw-page__smart-bar-amount')
            .assert.containsText('.sw-grid-column.sw-grid__cell.sw-grid-column--left', 'I am Groot');
    },
    after: (browser) => {
        browser.end();
    }
};
