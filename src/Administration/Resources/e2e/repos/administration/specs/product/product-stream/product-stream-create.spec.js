const productStreamPage = require('administration/page-objects/module/sw-product-stream.page-object.js');

module.exports = {
    '@tags': ['product', 'product-stream', 'product-stream-create', 'create'],
    '@disabled': !global.flags.isActive('next739'),
    'navigate to product stream and click on add product stream': (browser) => {
        browser
            .openMainMenuEntry('#/sw/product/index', 'Product', '#/sw/product/stream/index', 'Product streams')
            .waitForElementPresent('.sw-button__content')
            .click('.sw-button__content');
    },
    'create new product stream with basic condition': (browser) => {
        const page = productStreamPage(browser);

        browser
            .waitForElementVisible('.sw-product-stream-detail .sw-card__content')
            .assert.urlContains('#/sw/product/stream/create')
            .assert.containsText(page.elements.smartBarHeader, 'New product stream');

        page.createBasicProductStream('Product stream 1st', 'My first product stream');
    },
    'check if new product stream exists in overview': (browser) => {
        const page = productStreamPage(browser);

        browser
            .openMainMenuEntry('#/sw/product/index', 'Product', '#/sw/product/stream/index', 'Product streams')
            .refresh()
            .waitForElementPresent('.sw-button__content')
            .assert.urlContains('#/sw/product/stream/index')
            .assert.containsText(page.elements.smartBarHeader, 'Product stream')
            .waitForElementVisible(`${page.elements.gridRow}--0  ${page.elements.contextMenuButton}`)
            .assert.containsText(`${page.elements.gridRow}--0 `, 'Product stream 1st');
    },
    'verify product stream details': (browser) => {
        const page = productStreamPage(browser);

        browser
            .waitForElementPresent('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .click('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .waitForElementVisible(`${page.elements.gridRow}--0 ${page.elements.contextMenuButton}`)
            .clickContextMenuItem('.sw_product_stream_list__edit-action', page.elements.contextMenuButton, `${page.elements.gridRow}--0`)
            .waitForElementNotPresent(page.elements.loader)
            .waitForElementVisible(`${page.elements.smartBarHeader} h2:not(.sw-product-stream-detail__empty-title)`)
            .assert.containsText(page.elements.smartBarHeader, 'Product stream 1st');
    },
    after: (browser) => {
        browser.end();
    }
};
