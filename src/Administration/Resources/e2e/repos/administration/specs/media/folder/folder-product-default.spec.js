const mediaPage = require('administration/page-objects/sw-media.page-object.js');
const productPage = require('administration/page-objects/sw-product.page-object.js');

const fixtures = {
    name: 'Products for the good'
};

module.exports = {
    '@tags': ['media', 'folder', 'folder-product-default', 'set-default', 'product', 'upload'],
    '@disabled': !global.flags.isActive('next1207'),
    before: (browser, done) => {
        global.MediaFixtureService.setFolderFixture(fixtures).then(() => {
            return global.ProductFixtureService.setProductFixtures();
        }).then(() => {
            done();
        });
    },
    'open media listing and navigate to settings': (browser) => {
        const page = mediaPage(browser);
        page.openMediaIndex();

        browser
            .waitForElementVisible('.sw-media-base-item__preview-container')
            .click('.sw-media-base-item__preview-container')
            .waitForElementVisible('.quickaction--settings')
            .click('.quickaction--settings')
            .waitForElementVisible('.sw-modal__title')
            .assert.containsText('.sw-modal__title', 'Products for the good');
    },
    'set as default for products': (browser) => {
        const page = mediaPage(browser);

        browser
            .waitForElementVisible('.sw-media-folder-settings-modal__default-folder-select.sw-select')
            .assert.containsText('.sw-media-folder-settings-modal__default-folder-select.sw-select label', 'Default location for:')
            .fillSwSelectComponent(
                '.sw-media-folder-settings-modal__default-folder-select .sw-select__inner',
                {
                    value: 'Product Media',
                    isMulti: false,
                    searchTerm: 'Product Media'
                }
            )
            .waitForElementVisible('.sw-media-folder-settings-modal__default-folder-select .sw-select__single-selection')
            .assert.containsText('.sw-media-folder-settings-modal__default-folder-select .sw-select__single-selection', 'Product Media')
            .waitForElementNotPresent('.sw-select__results')
            .waitForElementVisible(page.elements.saveSettingsAction)
            .click(page.elements.saveSettingsAction)
            .checkNotification('Settings have been saved successfully')
            .waitForElementVisible('.icon--default-symbol-products');
    },
    'check if the folder is used as default location when uploading in products': (browser) => {
        browser
            .openMainMenuEntry('#/sw/product/index', 'Products')
            .waitForElementPresent('.smart-bar__actions a[href="#/sw/product/create"]')
            .waitForElementVisible('.sw-product-list__column-product-name')
            .assert.containsText('.sw-product-list__column-product-name', global.ProductFixtureService.productFixture.name)
            .clickContextMenuItem('.sw_product_list__edit-action', '.sw-context-button__button')
            .expect.element('.smart-bar__header h2').to.have.text.that.equals(global.ProductFixtureService.productFixture.name);
    },
    'upload product image and verify location in sidebar': (browser) => {
        const productPageObject = productPage(browser);
        const mediaPageObject = mediaPage(browser);

        productPageObject.addProductImageViaUrl(`${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`, global.ProductFixtureService.productFixture.name);

        browser
            .waitForElementVisible('.sw-media-preview__item')
            .getAttribute('.sw-media-preview__item', 'src', function (result) {
                this.assert.ok(result.value);
                this.assert.notEqual(result.value, `${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`);
            })
            .waitForElementVisible('.sw-sidebar-navigation-item')
            .click('.sw-sidebar-navigation-item')
            .waitForElementVisible('.sw-media-folder-item .icon--default-symbol-products')
            .expect.element(mediaPageObject.elements.folderNameLabel).to.have.text.that.equals(fixtures.name);

        browser
            .click('.sw-media-folder-item')
            .expect.element(mediaPageObject.elements.mediaNameLabel).to.have.text.that.equals('sw-login-background.png');
    },
    'verify if the product image is located in its corresponding media folder': (browser) => {
        const page = mediaPage(browser);
        page.openMediaIndex();
        browser
            .waitForElementNotPresent(page.elements.mediaNameLabel)
            .waitForElementVisible(page.elements.folderNameLabel)
            .waitForElementVisible('.sw-media-base-item__preview-container')
            .clickContextMenuItem(page.elements.showMediaAction, '.sw-context-button__button')
            .waitForElementVisible('.icon--folder-thumbnail-back')
            .waitForElementVisible('.smart-bar__header')
            .expect.element('.smart-bar__header').to.have.text.that.equals(fixtures.name);

        browser.expect.element(page.elements.mediaNameLabel).to.have.text.that.equals('sw-login-background.png');
    },
    after: (browser) => {
        browser.end();
    }
};
