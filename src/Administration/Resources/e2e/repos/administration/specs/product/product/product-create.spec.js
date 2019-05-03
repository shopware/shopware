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
                mainMenuId: 'sw-catalogue',
                subMenuId: 'sw-product'
            })
            .expect.element(page.elements.smartBarAmount).to.have.text.that.equals('(0)');
    },
    'go to create page, fill and save the new product': (browser) => {
        const page = productPage(browser);

        browser
            .click('a[href="#/sw/product/create"]')
            .assert.urlContains('#/sw/product/create')
            .expect.element(page.elements.cardTitle).to.have.text.that.equals('Information');

        page.createBasicProduct('Marci Darci');

        browser
            .waitForElementNotPresent(page.elements.loader)
            .waitForElementNotPresent('.icon--small-default-checkmark-line-medium')
            .getLocationInView('.sw-product-detail__select-category')
            .fillSwSelectComponent(
                '.sw-product-detail__select-category',
                {
                    value: global.AdminFixtureService.basicFixture.name,
                    isMulti: true,
                    searchTerm: global.AdminFixtureService.basicFixture.name
                }
            )
            .fillSwSelectComponent(
                '.sw-product-detail__select-visibility',
                {
                    value: 'Storefront',
                    isMulti: true,
                    searchTerm: 'Storefront'
                }
            )
            .expect.element(page.elements.productSaveAction).to.not.have.attribute('disabled');

        browser
            .waitForElementNotPresent('.icon--small-default-checkmark-line-medium')
            .click('.sw-product-detail__save-action')
            .waitForElementVisible('.icon--small-default-checkmark-line-medium')
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
            .fillGlobalSearchField('Marci Darci')
            .waitForElementNotPresent(page.elements.loader)
            .expect.element(page.elements.smartBarAmount).to.have.text.that.equals('(1)');
        browser.expect.element(page.elements.productListName).to.have.text.that.contains('Marci Darci');
    },
    'check if the data of the product is assigned correctly': (browser) => {
        const page = productPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-product-list__edit-action',
                scope: `${page.elements.dataGridRow}--0`
            })
            .expect.element('.sw-text-editor__content-editor').to.have.text.that.equals('My very first description');
        browser.click(page.elements.smartBarBack);
    },
    'check product in storefront': (browser) => {
        const page = productPage(browser);
        page.findInStorefront('Marci Darci');
        browser.expect.element('.product-detail-price').to.have.text.that.contains('99');
    }
};
