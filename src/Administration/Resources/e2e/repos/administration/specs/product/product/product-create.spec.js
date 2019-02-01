const productPage = require('administration/page-objects/module/sw-product.page-object.js');
const mediaPage = require('administration/page-objects/module/sw-media.page-object.js');

module.exports = {
    '@tags': ['product-create', 'product', 'create', 'upload'],
    before: (browser, done) => {
        global.FixtureService.create('category').then(() => {
            done();
        });
    },
    'open product listing': (browser) => {
        const page = productPage(browser);

        browser
            .assert.containsText('.sw-admin-menu__navigation-list-item.sw-product span.collapsible-text', 'Products')
            .click('a.sw-admin-menu__navigation-link[href="#/sw/product/index"]')
            .waitForElementVisible(page.elements.smartBarAmount)
            .assert.containsText(page.elements.smartBarAmount, '(0)');
    },
    'go to create page, fill and save the new product': (browser) => {
        const page = productPage(browser);

        browser
            .click('a[href="#/sw/product/create"]')
            .waitForElementVisible('.sw-product-detail-base')
            .assert.urlContains('#/sw/product/create')
            .assert.containsText(page.elements.cardTitle, 'Information');

        page.createBasicProduct('Marci Darci');

        browser
            .getLocationInView('.sw-product-detail__select-category')
            .fillSwSelectComponent(
                '.sw-product-detail__select-category',
                {
                    value: global.FixtureService.basicFixture.name,
                    isMulti: true,
                    searchTerm: global.FixtureService.basicFixture.name
                }
            )
            .click('.sw-product-detail__save-action')
            .checkNotification('Product "Marci Darci" has been saved successfully')
            .assert.urlContains('#/sw/product/detail');
    },
    'upload product image ': (browser) => {
        const page = productPage(browser);
        page.addProductImageViaUrl(`${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`, 'Marci Darci');

        browser
            .waitForElementVisible('.sw-media-preview__item')
            .getAttribute('.sw-media-preview__item', 'src', function (result) {
                this.assert.ok(result.value);
                this.assert.notEqual(result.value, `${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`);
            });
    },
    'go back to listing, search and verify creation': (browser) => {
        const page = productPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .refresh()
            .waitForElementVisible('.sw-product-list__content')
            .fillGlobalSearchField('Marci Darci')
            .waitForElementVisible(page.elements.smartBarAmount)
            .assert.containsText(page.elements.smartBarAmount, '(1)');
    },
    'check if the data of the product is assigned correctly': (browser) => {
        const page = productPage(browser);
        const mediaPageObject = mediaPage(browser);

        browser
            .refresh()
            .waitForElementVisible(page.elements.productListName)
            .assert.containsText(page.elements.productListName, 'Marci Darci')
            .clickContextMenuItem('.sw_product_list__edit-action', page.elements.contextMenuButton, `${page.elements.gridRow}--0`)
            .waitForElementVisible('.sw-product-detail-base')
            .waitForElementVisible(mediaPageObject.elements.previewItem)
            .waitForElementPresent('.sw-product-category-form .sw-select__selection-item')
            .assert.containsText('.ql-editor', 'My very first description')
            .getLocationInView('.sw-select__selection-item')
            .assert.containsText('.sw-select__selection-item', global.FixtureService.basicFixture.name)
            .click(page.elements.smartBarBack);
    },
    after: (browser) => {
        browser.end();
    }
};
