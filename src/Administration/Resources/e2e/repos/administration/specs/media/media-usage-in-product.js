const productPage = require('administration/page-objects/module/sw-product.page-object.js');
const mediaPage = require('administration/page-objects/module/sw-media.page-object.js');

const fixture = {
    name: 'Ultimate Product',
    descriptionLong: 'This is THE product.'
};

module.exports = {
    '@tags': ['media', 'media-usage', 'media-usage-in-product'],
    before: (browser, done) => {
        global.ProductFixtureService.setProductFixture(fixture).then(() => {
            done();
        });
    },
    'open product listing and select product': (browser) => {
        const page = productPage(browser);

        browser
            .openMainMenuEntry({
                targetPath: '#/sw/product/index',
                mainMenuId: 'sw-product'
            })
            .waitForElementVisible(page.elements.productListName)
            .click(`${page.elements.productListName} a`)
            .expect.element(page.elements.smartBarHeader).to.have.text.that.equals(fixture.name);
    },
    'upload media item': (browser) => {
        const page = productPage(browser);
        page.addProductImageViaUrl(`${process.env.APP_URL}/bundles/administration/static/fixtures/sw-test-image.png`, 'Ultimate Product');

        browser
            .click(page.elements.primaryButton)
            .checkNotification('Product "Ultimate Product" has been saved successfully.');
    },
    'verify upload in media module': (browser) => {
        const page = mediaPage(browser);
        page.openMediaIndex();

        browser
            .waitForElementNotPresent(page.elements.loader)
            .waitForElementVisible(`${page.elements.gridItem}--0`)
            .click(`${page.elements.gridItem}--0`)
            .waitForElementVisible(`${page.elements.gridItem}--0`)
            .click(`${page.elements.gridItem}--0`)
            .waitForElementNotPresent('sw-media-sidebar.no-headline')
            .expect.element('.sw-media-sidebar__headline').to.have.text.that.equals('sw-test-image.png');

        browser
            .getLocationInView('.sw-media-quickinfo-usage')
            .expect.element('.sw-media-quickinfo-usage__item').to.have.text.that.equals('Ultimate Product');
    },
    after: (browser) => {
        browser.end();
    }
};
