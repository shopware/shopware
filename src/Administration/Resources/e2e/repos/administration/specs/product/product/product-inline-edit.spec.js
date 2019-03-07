const productPage = require('administration/page-objects/module/sw-product.page-object.js');

const fixture = {
    name: 'Beautiful Product'
};

module.exports = {
    '@tags': ['product', 'product-inline-edit', 'inline-edit'],
    before: (browser, done) => {
        global.ProductFixtureService.setProductFixture(fixture).then(() => {
            done();
        });
    },
    'open product listing and look for the product to be edited': (browser) => {
        const page = productPage(browser);

        browser
            .openMainMenuEntry({
                targetPath: '#/sw/product/index',
                mainMenuId: 'sw-product'
            })
            .expect.element(page.elements.smartBarAmount).to.have.text.that.equals('(1)');

        browser.expect.element(page.elements.productListName).to.have.text.that.contains(fixture.name);
    },
    'edit product name via inline editing and verify edit': (browser) => {
        const page = productPage(browser);

        browser
            .waitForElementVisible(`${page.elements.dataGridRow}--0`)
            .moveToElement(`${page.elements.dataGridRow}--0`, 5, 5).doubleClick()
            .fillField(`${page.elements.dataGridRow}--0 ${page.elements.productListName} input[type=text]`, 'Cyberdyne Systems T800', true)
            .click(`${page.elements.dataGridRow}--0 ${page.elements.dataGridInlineEditSave}`)
            .waitForElementNotPresent('.is--inline-edit')
            .refresh()
            .expect.element(page.elements.productListName).to.have.text.that.contains('Cyberdyne Systems T800');
    },
    after: (browser) => {
        browser.end();
    }
};
