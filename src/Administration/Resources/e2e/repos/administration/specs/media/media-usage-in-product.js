const productPage = require('administration/page-objects/module/sw-product.page-object.js');
const mediaPage = require('administration/page-objects/module/sw-media.page-object.js');

module.exports = {
    '@tags': ['media', 'media-usage', 'media-usage-in-product'],
    before: (browser, done) => {
        global.ProductFixtureService.setProductFixture({
            name: 'Ultimate Product',
            descriptionLong: 'This is THE product.'
        }).then(() => {
            done();
        });
    },
    'open product listing and select product': (browser) => {
        const page = productPage(browser);
        browser
            .openMainMenuEntry('#/sw/product/index', 'Product')
            .waitForElementVisible(page.elements.contextMenuButton)
            .clickContextMenuItem(page.elements.contextMenu, page.elements.contextMenuButton);
    },
    'upload media item': (browser) => {
        const page = productPage(browser);
        page.addProductImageViaUrl(`${process.env.APP_URL}/bundles/administration/static/fixtures/sw-test-image.png`, 'Ultimate Product');
        browser
            .click(page.elements.primaryButton)
            .checkNotification('Product "Ultimate Product" has been saved successfully.', true);
    },
    'verify upload in media module': (browser) => {
        const page = mediaPage(browser);
        browser
            .openMainMenuEntry('#/sw/media/index', 'Media')
            .waitForElementVisible(page.elements.folderItem)
            .moveToElement(page.elements.folderItem, 5, 5)
            .click(page.elements.folderItem)
            .waitForElementVisible(page.elements.mediaItem)
            .moveToElement(page.elements.mediaItem, 5, 5)
            .click(page.elements.mediaItem)
            .waitForElementNotPresent('sw-media-sidebar.no-headline')
            .assert.containsText('.sw-media-sidebar__headline', 'sw-test-image.png')
            .getLocationInView('.sw-media-quickinfo-usage')
            .waitForElementVisible('.sw-media-quickinfo-usage__item')
            .assert.containsText('.sw-media-quickinfo-usage__item', 'Ultimate Product');
    },
    after: (browser) => {
        browser.end();
    }
};
