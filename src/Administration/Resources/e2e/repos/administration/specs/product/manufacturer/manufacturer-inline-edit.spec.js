const manufacturerPage = require('administration/page-objects/module/sw-manufacturer.page-object.js');

module.exports = {
    '@tags': ['manufacturer-inline-edit', 'manufacturer', 'inline-edit'],
    before: (browser, done) => {
        global.AdminFixtureService.create('product-manufacturer').then(() => {
            done();
        });
    },
    'navigate to manufacturer module': (browser) => {
        browser
            .openMainMenuEntry({
                mainMenuPath: '#/sw/product/index',
                menuTitle: 'Product',
                index: 1,
                subMenuItemPath: '#/sw/manufacturer/index',
                subMenuTitle: 'Manufacturer'
            });
    },
    'inline edit manufacturer name and website and verify edits': (browser) => {
        const page = manufacturerPage(browser);

        browser
            .waitForElementVisible(`${page.elements.gridRow}--0 > .sw-manufacturer-list_column-manufacturer-description`)
            .moveToElement(`${page.elements.gridRow}--0 > .sw-manufacturer-list_column-manufacturer-description`, 5, 5)
            .doubleClick()
            .waitForElementPresent('.is--inline-editing ')
            .fillField(`${page.elements.gridRow}--0 input[name=sw-field--item-name]`, 'I am Groot', true)
            .waitForElementVisible(page.elements.gridRowInlineEdit);
    },
    after: (browser) => {
        browser.end();
    }
};
