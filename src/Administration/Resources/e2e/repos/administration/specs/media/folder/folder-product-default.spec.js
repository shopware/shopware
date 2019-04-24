const mediaPage = require('administration/page-objects/module/sw-media.page-object.js');
const productPage = require('administration/page-objects/module/sw-product.page-object.js');

const fixtures = {
    name: 'A Product for the good'
};

module.exports = {
    '@tags': ['media', 'folder', 'folder-product-default', 'set-default', 'product', 'upload'],
    before: (browser, done) => {
        global.MediaFixtureService.setFolderFixture(fixtures).then(() => {
            return global.ProductFixtureService.setProductFixture();
        }).then(() => {
            done();
        });
    },
    'open media listing and navigate to settings': (browser) => {
        const page = mediaPage(browser);
        page.openMediaIndex();

        browser
            .waitForElementNotPresent(page.elements.loader);

        page.openMediaModal(page.elements.showSettingsAction, 0);

        browser.expect.element(`${page.elements.modal}__title`).to.have.text.that.equals('A Product for the good');
    },
    'set as default for products': (browser) => {
        const page = mediaPage(browser);

        browser.expect.element('.sw-media-folder-settings-modal__default-folder-select.sw-select label').to.have.text.that.equals('Default location for:');

        browser
            .fillSwSelectComponent(
                '.sw-media-folder-settings-modal__default-folder-select .sw-select__inner',
                {
                    value: 'Product Media',
                    isMulti: false,
                    searchTerm: 'Product Media'
                }
            )
            .expect.element('.sw-media-folder-settings-modal__default-folder-select .sw-select__single-selection').to.have.text.that.equals('Product Media');

        browser
            .waitForElementNotPresent('.sw-select__results')
            .click(page.elements.saveSettingsAction)
            .checkNotification('Settings have been saved successfully')
            .waitForElementVisible('.icon--default-symbol-products');
    },
    'check if the folder is used as default location when uploading in products': (browser) => {
        const productPageObject = productPage(browser);

        browser
            .openMainMenuEntry({
                targetPath: '#/sw/product/index',
                mainMenuId: 'sw-product'
            })
            .waitForElementPresent('.smart-bar__actions a[href="#/sw/product/create"]')
            .expect.element(`${productPageObject.elements.productListName}`).to.have.text.that.contains(global.ProductFixtureService.productFixture.name);

        browser
            .click(`${productPageObject.elements.productListName} a`)
            .expect.element(productPageObject.elements.smartBarHeader).to.have.text.that.equals(global.ProductFixtureService.productFixture.name);
    },
    'upload product image and verify location in sidebar': (browser) => {
        const productPageObject = productPage(browser);
        const mediaPageObject = mediaPage(browser);

        productPageObject.addProductImageViaUrl(`${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`, global.ProductFixtureService.productFixture.name);

        browser.expect.element(mediaPageObject.elements.previewItem).to.have.attribute('src').contains('sw-login-background.png');

        browser
            .click('.sw-sidebar-navigation-item')
            .expect.element('.sw-sidebar-media-item__content').to.have.text.that.contains(fixtures.name);
    },
    'verify if the product image is located in its corresponding media folder': (browser) => {
        const page = mediaPage(browser);
        page.openMediaIndex();
        browser
            .waitForElementVisible(`${page.elements.gridItem}--0 .sw-media-base-item__preview-container`)
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: page.elements.showMediaAction,
                scope: `${page.elements.gridItem}--0`
            })
            .expect.element(page.elements.smartBarHeader).to.have.text.that.equals(fixtures.name);

        browser.expect.element(page.elements.mediaNameLabel).to.have.text.that.equals('sw-login-background.png');
    }
};
