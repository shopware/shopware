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
            .openMainMenuEntry({
                mainMenuPath: '#/sw/product/index',
                menuTitle: 'Product',
                index: 1,
                subMenuItemPath: '#/sw/stream/index',
                subMenuTitle: 'Product streams'
            })
            .expect.element(`${page.elements.gridRow}--0`).to.have.text.that.contains(global.FixtureService.basicFixture.name).before(browser.globals.waitForConditionTimeout);
    },
    'open product stream details and change the given data': (browser) => {
        const page = productStreamPage(browser);

        browser
            .waitForElementPresent('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .click('.sw-sidebar__navigation .sw-sidebar-navigation-item')
            .waitForElementVisible(`${page.elements.gridRow}--0 ${page.elements.contextMenuButton}`)
            .clickContextMenuItem('.sw_product_stream_list__edit-action', page.elements.contextMenuButton, `${page.elements.gridRow}--0`)
            .waitForElementNotPresent(page.elements.loader)
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains('1st product stream').before(browser.globals.waitForConditionTimeout);

        browser
            .fillField('input[name=sw-field--productStream-name]', 'Edited product stream', true)
            .fillField('textarea[name=sw-field--productStream-description]', 'The product stream was edited by an e2e test', true)
            .waitForElementVisible(page.elements.streamSaveAction)
            .click(page.elements.streamSaveAction)
            .checkNotification('The product stream "Edited product stream" was saved.');
    },
    'check if updated product stream exists in overview': (browser) => {
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
            .expect.element(`${page.elements.gridRow}--0`).to.have.text.that.contains('Edited product stream').before(browser.globals.waitForConditionTimeout);
        browser.assert.urlContains('#/sw/product/stream/index')
    },
    after: (browser) => {
        browser.end();
    }
};
