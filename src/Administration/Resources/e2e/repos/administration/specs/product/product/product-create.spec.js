const productPage = require('administration/page-objects/module/sw-product.page-object.js');
const mediaPage = require('administration/page-objects/module/sw-media.page-object.js');

module.exports = {
    '@tags': ['product-create', 'product', 'create', 'upload'],
    before: (browser, done) => {
        global.AdminFixtureService.create('category').then(() => {
            done();
        });
    },
    'open product listing': (browser) => {
        const page = productPage(browser);

        browser
            .openMainMenuEntry({
                targetPath: '#/sw/product/index',
                mainMenuId: 'sw-product'
            })
            .expect.element(page.elements.smartBarAmount).to.have.text.that.equals('(0)');
    },
    'go to create page, fill and save the new product': (browser) => {
        const page = productPage(browser);

        browser
            .click('a[href="#/sw/product/create"]')
            .waitForElementVisible('.sw-product-detail-base')
            .assert.urlContains('#/sw/product/create')
            .expect.element(page.elements.cardTitle).to.have.text.that.equals('Information');

        page.createBasicProduct('Marci Darci');

        browser
            .getLocationInView('.sw-product-detail__select-category')
            .fillSwSelectComponent(
                '.sw-product-detail__select-category',
                {
                    value: global.AdminFixtureService.basicFixture.name,
                    isMulti: true,
                    searchTerm: global.AdminFixtureService.basicFixture.name
                }
            )
            .click('.sw-product-detail__save-action')
            .checkNotification('Product "Marci Darci" has been saved successfully')
            .assert.urlContains('#/sw/product/detail');
    },
    'upload product image ': (browser) => {
        const page = productPage(browser);
        const mediaPageObject = mediaPage(browser);

        page.addProductImageViaUrl(`${process.env.APP_URL}/bundles/administration/static/fixtures/sw-login-background.png`, 'Marci Darci');

        browser.expect.element(mediaPageObject.elements.previewItem).to.have.attribute('src').contains('sw-login-background.png');
    },
    'go back to listing, search and verify creation': (browser) => {
        const page = productPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .refresh()
            .waitForElementVisible('.sw-product-list__content')
            .fillGlobalSearchField('Marci Darci')
            .expect.element(page.elements.smartBarAmount).to.have.text.that.equals('(1)');
    },
    'check if the data of the product is assigned correctly': (browser) => {
        const page = productPage(browser);
        const mediaPageObject = mediaPage(browser);

        browser
            .refresh()
            .expect.element(page.elements.productListName).to.have.text.that.contains('Marci Darci');

        browser
            .clickContextMenuItem('.sw_product_list__edit-action', page.elements.contextMenuButton, `${page.elements.gridRow}--0`)
            .waitForElementVisible(mediaPageObject.elements.previewItem)
            .expect.element('.ql-editor').to.have.text.that.equals('My very first description');

        browser
            .getLocationInView('.sw-select__selection-item')
            .expect.element('.sw-select__selection-item').to.have.text.that.equals(global.AdminFixtureService.basicFixture.name);
        browser.click(page.elements.smartBarBack);
    },
    after: (browser) => {
        browser.end();
    }
};
