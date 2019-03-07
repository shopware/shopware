const productPage = require('administration/page-objects/module/sw-product.page-object.js');

module.exports = {
    '@tags': ['product', 'product-edit', 'edit'],
    before: (browser, done) => {
        global.ProductFixtureService.setProductFixture({
            name: "Really good product",
            description: "This describes a product. It is your product. You will take care of your product. You will set a price, keep records of storage quantities and take care of whatever needs your product might develop. You love your product. Your are the product. Now go find someone dumb enough to buy your precious product."
        }).then(() => {
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
            .expect.element(page.elements.smartBarAmount).to.have.text.that.contains('(1)');
    },
    'edit product': (browser) => {
        const page = productPage(browser);

        browser
            .clickContextMenuItem('.sw-context-menu-item__text', page.elements.contextMenuButton, `${page.elements.dataGridRow}--0`)
            .waitForElementNotPresent(`.product-basic-form ${page.elements.loader}`)
            .fillField('input[name=sw-field--product-name]', 'Geändertes, immernoch supergeiles Produkt', true)
            .fillField('.ql-editor', 'Cant you see this is a great product?', true, 'editor')
            .click(page.elements.productSaveAction)
            .checkNotification('Product "Geändertes, immernoch supergeiles Produkt" has been saved successfully.');
    },
    after: (browser) => {
        browser.end();
    }
};
