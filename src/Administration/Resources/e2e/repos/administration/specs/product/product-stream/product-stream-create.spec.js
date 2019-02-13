const productStreamPage = require('administration/page-objects/module/sw-product-stream.page-object.js');

module.exports = {
    '@tags': ['product', 'product-stream', 'product-stream-create', 'create'],
    '@disabled': !global.flags.isActive('next739'),
    'navigate to product stream and click on add product stream': (browser) => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/product-stream/index',
                mainMenuId: 'sw-product'
            })
            .waitForElementPresent('.sw-button__content')
            .click('.sw-button__content');
    },
    'create new product stream with basic condition': (browser) => {
        const page = productStreamPage(browser);

        browser
            .waitForElementPresent('.sw-button__content')
            .click('.sw-button__content')
            .waitForElementVisible('.sw-product-stream-detail .sw-card__content')
            .expect.element(page.elements.smartBarHeader).to.have.text.that.equals('New product stream');
        browser.assert.urlContains('#/sw/product/stream/create');

        page.createBasicProductStream('Product stream 1st', 'My first product stream');
    },
    'check if new product stream exists in overview': (browser) => {
        const page = productStreamPage(browser);

        browser
            .openMainMenuEntry({
                mainMenuPath: '#/sw/product/index',
                menuTitle: 'Product',
                index: 1,
                subMenuItemPath: '#/sw/stream/index',
                subMenuTitle: 'Product streams'
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
            .waitForElementVisible(`${page.elements.gridRow}--0 ${page.elements.contextMenuButton}`)
            .clickContextMenuItem('.sw_product_stream_list__edit-action', page.elements.contextMenuButton, `${page.elements.gridRow}--0`)
            .waitForElementNotPresent(page.elements.loader)
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains('Product stream 1st');
    },
    after: (browser) => {
        browser.end();
    }
};
