const manufacturerPage = require('administration/page-objects/module/sw-manufacturer.page-object.js');

module.exports = {
    '@tags': ['product', 'manufacturer-edit', 'manufacturer', 'edit'],
    before: (browser, done) => {
        global.AdminFixtureService.create('product-manufacturer').then(() => {
            done();
        });
    },
    'navigate to manufacturer module and look for manufacturer to be edited': (browser) => {
        const page = manufacturerPage(browser);

        browser
            .openMainMenuEntry({
                targetPath: '#/sw/product/index',
                mainMenuId: 'sw-product'
            })
            .expect.element(`${page.elements.gridRow}--0`).to.have.text.that.equals(global.AdminFixtureService.basicFixture.name);
    },
    'open manufacturer details and change the given data': (browser) => {
        const page = manufacturerPage(browser);

        browser
            .refresh()
            .clickContextMenuItem('.sw-manufacturer-list__edit-action', page.elements.contextMenuButton, `${page.elements.gridRow}--0`)
            .waitForElementNotPresent(page.elements.loader)
            .expect.element(page.elements.smartBarHeader).to.have.text.that.equals('MAN-U-FACTURE');
    },
    after: (browser) => {
        browser.end();
    }
};
