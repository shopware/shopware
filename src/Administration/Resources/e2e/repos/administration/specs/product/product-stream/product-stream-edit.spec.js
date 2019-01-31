module.exports = {
    '@tags': ['product', 'product-stream-edit', 'product-stream', 'edit'],
    '@disabled': !global.flags.isActive('next739'),
    before: (browser, done) => {
        global.FixtureService.create('product-stream').then(() => {
            done();
        });
    },
    'navigate to product stream module and look for product stream to be edited': (browser) => {
        browser
            .openMainMenuEntry('#/sw/product/index', 'Product', '#/sw/product/stream/index', 'Product streams')
            .assert.urlContains('#/sw/product/stream/index')
            .waitForElementVisible('.sw-grid-row:first-child .sw-context-button__button')
            .assert.containsText('.sw-grid-row:first-child', global.FixtureService.basicFixture.name);
    },
    'open product stream details and change the given data': (browser) => {
        browser
            .waitForElementPresent('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .click('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .waitForElementVisible('.sw-grid-row:first-child .sw-context-button__button')
            .clickContextMenuItem('.sw_product_stream_list__edit-action', '.sw-context-button__button', '.sw-grid-row:first-child')
            .waitForElementNotPresent('.sw-loader')
            .waitForElementVisible('.smart-bar__header h2:not(.sw-product-stream-detail__empty-title)')
            .assert.containsText('.smart-bar__header', '1st product stream')
            .fillField('input[name=sw-field--productStream-name]', 'Edited product stream', true)
            .fillField('textarea[name=sw-field--productStream-description]', 'The product stream was edited by an e2e test', true)
            .waitForElementVisible('.sw-product-stream-detail__save-action')
            .click('.sw-product-stream-detail__save-action')
            .checkNotification('The product stream "Edited product stream" was saved.')
            .click('.sw-button__content');
    },
    'check if updated product stream exists in overview': (browser) => {
        browser
            .openMainMenuEntry('#/sw/product/index', 'Product', '#/sw/product/stream/index', 'Product streams')
            .refresh()
            .waitForElementPresent('.sw-button__content')
            .assert.urlContains('#/sw/product/stream/index')
            .assert.containsText('.smart-bar__header', 'Product stream')
            .waitForElementVisible('.sw-grid-row:first-child .sw-context-button__button')
            .assert.containsText('.sw-grid-row:first-child', 'Edited product stream');
    },
    after: (browser) => {
        browser.end();
    }
};
