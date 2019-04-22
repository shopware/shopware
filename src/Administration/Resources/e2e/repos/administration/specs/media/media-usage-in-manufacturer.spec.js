const manufacturerPage = require('administration/page-objects/module/sw-manufacturer.page-object.js');
const mediaPage = require('administration/page-objects/module/sw-media.page-object.js');

module.exports = {
    '@tags': ['media', 'media-usage', 'media-usage-in-manufacturer'],
    before: (browser, done) => {
        global.ProductFixtureService.setProductFixture({}).then(() => {
            done();
        });
    },
    'open manufacturer listing and select manufacturer': (browser) => {
        const page = manufacturerPage(browser);

        browser
            .openMainMenuEntry({
                targetPath: '#/sw/manufacturer/index',
                mainMenuId: 'sw-product',
                subMenuId: 'sw-manufacturer'
            })
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: page.elements.contextMenu
            });
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
        page.openMediaIndex();

        browser
            .waitForElementNotPresent(page.elements.loader)
            .waitForElementVisible(`${page.elements.gridItem}--2`)
            .click(`${page.elements.gridItem}--2`)
            .expect.element('.sw-media-sidebar__headline').to.have.text.that.equals('Product Manufacturer Media');

        browser
            .waitForElementVisible(`${page.elements.gridItem}--0`)
            .click(`${page.elements.gridItem}--0`)
            .waitForElementNotPresent('sw-media-sidebar.no-headline')
            .expect.element('.sw-media-sidebar__headline').to.have.text.that.equals('sw-test-image.png');

        browser
            .getLocationInView('.sw-media-quickinfo-usage')
            .expect.element('.sw-media-quickinfo-usage__item').to.have.text.that.equals('shopware AG');
    }
};
