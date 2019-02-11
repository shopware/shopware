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
            .openMainMenuEntry('#/sw/product/index', 'Product', '#/sw/product/stream/index', 'Product streams')
    },
    'check if new product stream exists in overview': (browser) => {
        const page = productStreamPage(browser);

        browser
            .waitForElementPresent('.sw-button__content')
            .assert.urlContains('#/sw/product/stream/index')
            .assert.containsText(page.elements.smartBarHeader, 'Product streams')
            .waitForElementVisible(`${page.elements.gridRow}--0 ${page.elements.contextMenuButton}`)
            .assert.containsText(`${page.elements.gridRow}--0`, global.AdminFixtureService.basicFixture.name);
    },
    'verify product stream details': (browser) => {
        const page = productStreamPage(browser);

        browser
            .waitForElementPresent('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .click('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .waitForElementVisible(`${page.elements.gridRow}--0 ${page.elements.contextMenuButton}`)
            .click(`${page.elements.gridRow}--0 ${page.elements.contextMenuButton}`)
            .waitForElementVisible(page.elements.contextMenu)
            .click(`${page.elements.contextMenu} .sw-context-menu-item__text`)
            .waitForElementNotPresent(page.elements.loader)
            .waitForElementVisible(`${page.elements.smartBarHeader} h2:not(.sw-product-stream-detail__empty-title)`)
            .assert.containsText(page.elements.smartBarHeader, global.AdminFixtureService.basicFixture.name);
    },
    'delete product stream': (browser) => {
        const page = productStreamPage(browser);

        browser
            .openMainMenuEntry('#/sw/product/index', 'Product', '#/sw/product/stream/index', 'Product streams')
            .waitForElementVisible(page.elements.smartBarAmount)
            .assert.containsText(page.elements.smartBarAmount, '(1)');

        page.deleteProductStream(global.AdminFixtureService.basicFixture.name);

        browser
            .waitForElementNotPresent(page.elements.loader)
            .waitForElementPresent(page.elements.smartBarAmount)
            .assert.containsText(page.elements.smartBarAmount, '(0)');
    },
    after: (browser) => {
        browser.end();
    }
};
