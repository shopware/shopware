const productStreamPage = require('administration/page-objects/module/sw-product-stream.page-object.js');

module.exports = {
    '@tags': ['product', 'product-stream-edit', 'product-stream', 'edit'],
    '@disabled': !global.flags.isActive('next739'),
    before: (browser, done) => {
        global.AdminFixtureService.create('product-stream').then(() => {
            done();
        });
    },
    'navigate to product stream module and look for product stream to be edited': (browser) => {
        const page = productStreamPage(browser);

        browser
            .openMainMenuEntry('#/sw/product/index', 'Product', '#/sw/product/stream/index', 'Product streams')
            .waitForElementVisible(`${page.elements.gridRow}--0  ${page.elements.contextMenuButton}`)
            .assert.containsText(`${page.elements.gridRow}--0`, global.AdminFixtureService.basicFixture.name);
    },
    'open product stream details and change the given data': (browser) => {
        const page = productStreamPage(browser);

        browser
            .waitForElementPresent('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .click('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .waitForElementVisible(`${page.elements.gridRow}--0 ${page.elements.contextMenuButton}`)
            .clickContextMenuItem('.sw_product_stream_list__edit-action', page.elements.contextMenuButton, `${page.elements.gridRow}--0`)
            .waitForElementNotPresent(page.elements.loader)
            .waitForElementVisible(`${page.elements.smartBarHeader} h2:not(.sw-product-stream-detail__empty-title)`)
            .assert.containsText(page.elements.smartBarHeader, '1st product stream')
            .fillField('input[name=sw-field--productStream-name]', 'Edited product stream', true)
            .fillField('textarea[name=sw-field--productStream-description]', 'The product stream was edited by an e2e test', true)
            .waitForElementVisible(page.elements.streamSaveAction)
            .click(page.elements.streamSaveAction)
            .checkNotification('The product stream "Edited product stream" was saved.')
            .click('.sw-button__content');
    },
    'check if updated product stream exists in overview': (browser) => {
        const page = productStreamPage(browser);

        browser
            .openMainMenuEntry('#/sw/product/index', 'Product', '#/sw/product/stream/index', 'Product streams')
            .refresh()
            .waitForElementPresent('.sw-button__content')
            .assert.urlContains('#/sw/product/stream/index')
            .assert.containsText(page.elements.smartBarHeader, 'Product stream')
            .waitForElementVisible(`${page.elements.gridRow}--0 ${page.elements.contextMenuButton}`)
            .assert.containsText(`${page.elements.gridRow}--0`, 'Edited product stream');
    },
    after: (browser) => {
        browser.end();
    }
};
