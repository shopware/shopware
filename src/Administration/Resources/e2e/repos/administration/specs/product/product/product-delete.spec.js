const productPage = require('administration/page-objects/module/sw-product.page-object.js');

const fixture = {
    name: 'Soon be gone'
};

module.exports = {
    '@tags': ['product-delete', 'product', 'delete'],
    before: (browser, done) => {
        global.ProductFixtureService.setProductFixture(fixture).then(() => {
            done();
        });
    },
    'open product listing and look for the product to be deleted': (browser) => {
        const page = productPage(browser);

        browser
            .openMainMenuEntry({
                targetPath: '#/sw/product/index',
                mainMenuId: 'sw-product'
            })
            .expect.element(page.elements.smartBarAmount).to.have.text.that.equals('(1)');

        browser.expect.element(page.elements.productListName).to.have.text.that.equals(fixture.name);
    },
    'delete created product': (browser) => {
        const page = productPage(browser);


        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: `${page.elements.contextMenu}-item--danger`,
                scope: `${page.elements.dataGridRow}--0`
            })
            .expect.element(`${page.elements.modal} .sw-product-list__confirm-delete-text`).to.have.text.that
            .contains(`Are you sure you really want to delete the product "${fixture.name}"?`);

        browser
            .click(`${page.elements.modal}__footer button${page.elements.primaryButton}`)
            .waitForElementNotPresent(page.elements.modal)
            .waitForElementVisible(page.elements.emptyState)
            .expect.element(page.elements.smartBarAmount).to.have.text.that.contains('(0)');
    },
    'search for deleted product and expecting no result': (browser) => {
        const page = productPage(browser);

        browser
            .fillGlobalSearchField('Soon be gone')
            .waitForElementNotPresent(page.elements.loader)
            .waitForElementVisible(page.elements.emptyState)
            .expect.element(page.elements.smartBarAmount).to.have.text.that.contains('(0)');
    }
};
