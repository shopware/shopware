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
                targetPath: '#/sw/manufacturer/index',
                mainMenuId: 'sw-product',
                subMenuId: 'sw-manufacturer'
            })
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
            .waitForElementVisible(`${page.elements.gridRow}--0 ${page.elements.gridRowInlineEdit}`)
            .click(` ${page.elements.gridRowInlineEdit}`)
            .expect.element(`${page.elements.gridRow}--0`).to.have.text.that.contains('I am Groot');
    },
    after: (browser) => {
        browser.end();
    }
};
