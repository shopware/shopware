const mediaPage = require('administration/page-objects/module/sw-media.page-object.js');
const productPage = require('administration/page-objects/module/sw-product.page-object.js');


module.exports = {
    '@tags': ['media', 'media-replace', 'replace'],
    before: (browser, done) => {
        global.ProductFixtureService.setProductFixture().then(() => {
            done();
        });
    },
    'upload product image': (browser) => {
        const productPageObject = productPage(browser);
        const page = mediaPage(browser);

        browser
            .openMainMenuEntry({
                mainMenuPath: '#/sw/product/index',
                menuTitle: 'Products',
                index: 1
            })
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains('Products');

        browser
            .clickContextMenuItem('.sw_product_list__edit-action', '.sw-context-button__button')
            .expect.element(page.elements.smartBarHeader).to.have.text.that.equals(global.ProductFixtureService.productFixture.name).before(browser.globals.waitForConditionTimeout);

        productPageObject.addProductImageViaUrl(`${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`, global.ProductFixtureService.productFixture.name);

        browser
            .waitForElementVisible(page.elements.previewItem);
    },
    'open media listing and navigate to folder if necessary': (browser) => {
        const page = mediaPage(browser);
        page.openMediaIndex();

        browser
            .assert.urlContains('#/sw/media/index');

        if (global.flags.isActive('next1207')) {
            browser
                .waitForElementVisible(`${page.elements.gridItem}--0 .sw-media-base-item__preview-container`)
                .moveToElement(`${page.elements.gridItem}--0 .sw-media-base-item__preview-container`, 5, 5).doubleClick()
                .waitForElementVisible('.icon--folder-breadcrumbs-back-to-root');
        }
    },
    'open replace modal': (browser) => {
        const page = mediaPage(browser);
        page.openMediaModal('.sw-media-context-item__replace-media-action', 0);
    },
    'ensure image cannot be replaced with empty input': (browser) => {
        browser.expect.element('.sw-media-replace__replace-media-action').to.not.be.enabled;

        browser
            .waitForElementVisible('.sw-media-upload__switch-mode')
            .click('.sw-media-upload__switch-mode')
            .expect.element('.sw-media-replace__replace-media-action').to.not.be.enabled;
    },
    'replace image with a valid one': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible('.sw-media-url-form__url-input')
            .fillField('input[name=sw-field--url]', `${process.env.APP_URL}/bundles/administration/static/fixtures/sw-test-image.png`)
            .click('.sw-media-url-form__submit-button')
            .waitForElementNotPresent('input[name=sw-field--url]')
            .waitForElementVisible(`${page.elements.gridItem}--0 ${page.elements.previewItem}`)
            .waitForElementVisible('.sw-media-replace__replace-media-action')
            .click('.sw-media-replace__replace-media-action')
            .checkNotification('File successfully replaced.', `${page.elements.notification}--1`)
            .checkNotification('File has been saved successfully.');
    },
    'verify if image was replaced correctly': (browser) => {
        const page = mediaPage(browser);
        browser.expect.element(page.elements.mediaNameLabel).to.have.text.that.equals('sw-test-image.png');

        browser
            .waitForElementVisible(`${page.elements.gridItem}--0`)
            .click(`${page.elements.gridItem}--0`)
            .waitForElementVisible('.sw-media-quickinfo__media-preview')
            .waitForElementVisible('.sw-media-sidebar__headline')
            .assert.containsText('.sw-media-sidebar__headline', 'sw-test-image.png')
            .getLocationInView('.sw-media-sidebar__metadata-list');

        browser.expect.element('.sw-media-quickinfo-metadata-name input[name=sw-field--draft]').to.have.value.that.equals('sw-test-image');
        browser.expect.element('.sw-media-quickinfo-metadata-file-type').to.have.text.that.equals('PNG');
        browser.expect.element('.sw-media-quickinfo-metadata-mimeType').to.have.text.that.equals('image/png');
    },
    'verify if product image is replaced in module as well': (browser) => {
        const page = mediaPage(browser);

        browser
            .openMainMenuEntry({
                mainMenuPath: '#/sw/product/index',
                menuTitle: 'Products',
                index: 1
            })
            .waitForElementVisible(page.elements.smartBarHeader)
            .assert.containsText(page.elements.smartBarHeader, 'Products')
            .clickContextMenuItem('.sw_product_list__edit-action', '.sw-context-button__button')
            .expect.element(page.elements.previewItem).to.have.attribute('alt').equals('sw-test-image');
    },
    after: (browser) => {
        browser.end();
    }
};
