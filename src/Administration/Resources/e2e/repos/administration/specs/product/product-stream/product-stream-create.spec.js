const productStreamPage = require('administration/page-objects/module/sw-product-stream.page-object.js');

module.exports = {
    '@tags': ['product', 'product-stream', 'product-stream-create', 'create'],
    '@disabled': !global.flags.isActive('next739'),
    'navigate to product stream and click on add product stream': (browser) => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/product/stream/index',
                mainMenuId: 'sw-product',
                subMenuId: 'sw-product-stream'
            })
            .click('.sw-product-stream-list__create-action');
    },
    'create new product stream with basic condition': (browser) => {
        const page = productStreamPage(browser);

        browser.expect.element(page.elements.smartBarHeader).to.have.text.that.equals('New product stream');
        browser.assert.urlContains('#/sw/product/stream/create');

        page.createBasicProductStream('Product stream 1st', 'My first product stream');
    },
    'check if new product stream exists in overview': (browser) => {
        const page = productStreamPage(browser);

        browser
            .openMainMenuEntry({
                targetPath: '#/sw/product/stream/index',
                mainMenuId: 'sw-product',
                subMenuId: 'sw-product-stream'
            })
            .refresh()
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains('Product stream');
        browser.assert.urlContains('#/sw/product/stream/index');

        browser.expect.element(`${page.elements.gridRow}--0 `).to.have.text.that.contains('Product stream 1st');
    },
    'verify product stream details': (browser) => {
        const page = productStreamPage(browser);

        browser
            .waitForElementPresent('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .click('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw_product_stream_list__edit-action',
                scope: `${page.elements.gridRow}--0`
            })
            .waitForElementNotPresent(page.elements.loader)
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains('Product stream 1st');
    }
};
