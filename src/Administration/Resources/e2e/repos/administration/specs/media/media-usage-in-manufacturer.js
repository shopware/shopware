const manufacturerPage = require('administration/page-objects/module/sw-manufacturer.page-object.js');
const mediaPage = require('administration/page-objects/module/sw-media.page-object.js');

module.exports = {
    '@tags': ['media', 'media-usage', 'media-usage-in-manufacturer'],
    before: (browser, done) => {
        global.ProductFixtureService.setProductFixture({
        }).then(() => {
            done();
        });
    },
    'open manufacturer listing and select manufacturer': (browser) => {
        const page = manufacturerPage(browser);
        browser
            .openMainMenuEntry('#/sw/product/index', 'Product', '#/sw/manufacturer/index','Manufacturer')
            .waitForElementVisible(page.elements.contextMenuButton)
            .clickContextMenuItem(page.elements.contextMenu, page.elements.contextMenuButton);
    },
    'upload media item': (browser) => {
        const page = manufacturerPage(browser);
        page.addManufacturerLogo(`${process.env.APP_URL}/bundles/administration/static/fixtures/sw-test-image.png`);
        browser
            .click(page.elements.primaryButton)
            .checkNotification('Manufacturer "shopware AG" has been saved successfully.');
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
            .assert.containsText('.sw-media-quickinfo-usage__item', 'shopware AG');
    },
    after: (browser) => {
        browser.end();
    }
};
