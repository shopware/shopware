const productStreamPage = require('administration/page-objects/sw-product-stream.page-object.js');

module.exports = {
    '@tags': ['product', 'product-stream', 'product-stream-create', 'create'],
    '@disabled': !global.flags.isActive('next739'),
    'navigate to product stream and click on add product stream': (browser) => {
        browser
            .openMainMenuEntry('#/sw/product/index', 'Product', '#/sw/product/stream/index', 'Product streams')
            .assert.urlContains('#/sw/product/stream/index')
            .waitForElementPresent('.sw-button__content')
            .click('.sw-button__content');
    },
    'create new product stream with basic condition': (browser) => {
        const page = productStreamPage(browser);

        browser
            .waitForElementVisible('.sw-product-stream-detail .sw-card__content')
            .assert.urlContains('#/sw/product/stream/create')
            .assert.containsText('.smart-bar__header h2', 'New product stream');

        page.createBasicProductStream('Product stream 1st', 'My first product stream');
    },
    'check if new product stream exists in overview': (browser) => {
        browser
            .openMainMenuEntry('#/sw/product/index', 'Product', '#/sw/product/stream/index', 'Product streams')
            .refresh()
            .waitForElementPresent('.sw-button__content')
            .assert.urlContains('#/sw/product/stream/index')
            .assert.containsText('.smart-bar__header', 'Product stream')
            .waitForElementVisible('.sw-grid-row:first-child .sw-context-button__button')
            .assert.containsText('.sw-grid-row:first-child', 'Product stream 1st');
    },
    'verify product stream details': (browser) => {
        browser
            .waitForElementPresent('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .click('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .waitForElementVisible('.sw-grid-row:first-child .sw-context-button__button')
            .clickContextMenuItem('.sw_product_stream_list__edit-action', '.sw-context-button__button', '.sw-grid-row:first-child')
            .waitForElementNotPresent('.sw-loader')
            .waitForElementVisible('.smart-bar__header h2:not(.sw-product-stream-detail__empty-title)')
            .assert.containsText('.smart-bar__header', 'Product stream 1st');
    },
    after: (browser) => {
        browser.end();
    }
};
