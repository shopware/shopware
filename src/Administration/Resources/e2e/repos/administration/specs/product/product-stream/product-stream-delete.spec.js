const productStreamPage = require('administration/page-objects/sw-product-stream.page-object.js');

module.exports = {
    '@tags': ['product', 'product-stream-delete', 'product-stream', 'delete'],
    '@disabled': !global.flags.isActive('next739'),
    before: (browser, done) => {
        global.FixtureService.create('product-stream').then(() => {
            done();
        });
    },
    'navigate to product stream module': (browser) => {
        browser
            .openMainMenuEntry('#/sw/product/index', 'Product', '#/sw/product/stream/index', 'Product streams')
            .assert.urlContains('#/sw/product/stream/index');
    },
    'check if new product stream exists in overview': (browser) => {
        browser
            .waitForElementPresent('.sw-button__content')
            .assert.urlContains('#/sw/product/stream/index')
            .assert.containsText('.smart-bar__header', 'Product streams')
            .waitForElementVisible('.sw-grid-row:first-child .sw-context-button__button')
            .assert.containsText('.sw-grid-row:first-child', global.FixtureService.basicFixture.name);
    },
    'verify product stream details': (browser) => {
        browser
            .waitForElementPresent('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .click('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .waitForElementVisible('.sw-grid-row:first-child .sw-context-button__button')
            .click('.sw-grid-row:first-child .sw-context-button__button')
            .waitForElementVisible('.sw-context-menu')
            .click('.sw-context-menu .sw-context-menu-item__text')
            .waitForElementNotPresent('.sw-loader')
            .waitForElementVisible('.smart-bar__header h2:not(.sw-product-stream-detail__empty-title)')
            .assert.containsText('.smart-bar__header', global.FixtureService.basicFixture.name);
    },
    'delete product stream': (browser) => {
        browser
            .openMainMenuEntry('#/sw/product/index', 'Product', '#/sw/product/stream/index', 'Product streams');

        browser
            .waitForElementVisible('.sw-page__smart-bar-amount')
            .assert.containsText('.sw-page__smart-bar-amount', '(1)');

        const page = productStreamPage(browser);
        page.deleteProductStream(global.FixtureService.basicFixture.name);

        browser
            .waitForElementNotPresent('.sw-loader')
            .waitForElementPresent('.sw-page__smart-bar-amount')
            .assert.containsText('.sw-page__smart-bar-amount', '(0)');
    },
    after: (browser) => {
        browser.end();
    }
};
