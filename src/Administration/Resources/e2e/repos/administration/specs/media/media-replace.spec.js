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
                targetPath: '#/sw/product/index',
                mainMenuId: 'sw-product'
            })
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains('Products');

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-product-list__edit-action',
                scope: `${page.elements.dataGridRow}--0`
            })
            .expect.element(page.elements.smartBarHeader).to.have.text.that.equals(global.ProductFixtureService.productFixture.name);

        productPageObject.addProductImageViaUrl(`${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`, global.ProductFixtureService.productFixture.name);

        browser
            .waitForElementVisible(page.elements.previewItem);
    },
    'open media listing and navigate to folder if necessary': (browser) => {
        const page = mediaPage(browser);
        page.openMediaIndex();

        browser
            .waitForElementNotPresent(page.elements.loader)
            .assert.urlContains('#/sw/media/index');
    },
    'open replace modal': (browser) => {
        const page = mediaPage(browser);
        browser
            .click(`${page.elements.gridItem}--3`);
        page.openMediaModal('.sw-media-context-item__replace-media-action');
    },
    'ensure image cannot be replaced with empty input': (browser) => {
        browser.expect.element('.sw-media-replace__replace-media-action').to.not.be.enabled;

        browser
            .click('.sw-media-upload__switch-mode')
            .expect.element('.sw-media-replace__replace-media-action').to.not.be.enabled;
    },
    'replace image with a valid one': (browser) => {
        const page = mediaPage(browser);

        browser
            .fillField('input[name=sw-field--url]', `${process.env.APP_URL}/bundles/administration/static/fixtures/sw-test-image.png`)
            .click('.sw-media-url-form__submit-button')
            .waitForElementNotPresent('input[name=sw-field--url]')
            .waitForElementVisible(`${page.elements.mediaItem} ${page.elements.previewItem}`)
            .click('.sw-media-replace__replace-media-action')
            .checkNotification('File has been saved successfully.');
    },
    'verify if image was replaced correctly': (browser) => {
        const page = mediaPage(browser);
        browser.expect.element(page.elements.mediaNameLabel).to.have.text.that.equals('sw-test-image.png');

        browser
            .click(`${page.elements.mediaItem}`)
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
                targetPath: '#/sw/product/index',
                mainMenuId: 'sw-product'
            })
            .waitForElementVisible(page.elements.smartBarHeader)
            .assert.containsText(page.elements.smartBarHeader, 'Products')
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-product-list__edit-action',
                scope: `${page.elements.dataGridRow}--0`
            })
            .expect.element(page.elements.previewItem).to.have.attribute('alt').equals('sw-test-image');
    }
};
