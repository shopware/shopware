const productStreamPage = require('administration/page-objects/module/sw-product-stream.page-object.js');

module.exports = {
    '@tags': ['product', 'product-stream-delete', 'product-stream', 'delete'],
    '@disabled': !global.flags.isActive('next739'),
    before: (browser, done) => {
        global.AdminFixtureService.create('product-stream').then(() => {
            done();
        });
    },
    'navigate to product stream module': (browser) => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/product/stream/index',
                mainMenuId: 'sw-product',
                subMenuId: 'sw-product-stream'
            });
    },
    'check if new product stream exists in overview': (browser) => {
        const page = productStreamPage(browser);

        browser
            .assert.urlContains('#/sw/product/stream/index')
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains('Product streams');
        browser.expect.element(`${page.elements.gridRow}--0`).to.have.text.that.contains(global.AdminFixtureService.basicFixture.name);
    },
    'verify product stream details': (browser) => {
        const page = productStreamPage(browser);

        browser

            .click('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: `${page.elements.contextMenu} .sw-context-menu-item__text`,
                scope: `${page.elements.gridRow}--0`
            })
            .waitForElementNotPresent(page.elements.loader)
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains(global.AdminFixtureService.basicFixture.name);
    },
    'delete product stream': (browser) => {
        const page = productStreamPage(browser);

        browser
            .openMainMenuEntry({
                targetPath: '#/sw/product/stream/index',
                mainMenuId: 'sw-product',
                subMenuId: 'sw-product-stream'
            })
            .waitForElementVisible(page.elements.smartBarAmount)
            .assert.containsText(page.elements.smartBarAmount, '(1)');

        page.deleteProductStream(global.AdminFixtureService.basicFixture.name);

        browser
            .waitForElementNotPresent(page.elements.loader)
            .expect.element(page.elements.smartBarAmount).to.have.text.that.contains('(0)');
    }
};
