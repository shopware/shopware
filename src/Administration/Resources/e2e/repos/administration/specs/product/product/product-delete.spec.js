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
                mainMenuPath: '#/sw/product/index',
                menuTitle: 'Products',
                index: 1
            })
            .expect.element(page.elements.smartBarAmount).to.have.text.that.equals('(1)');

        browser.expect.element('.sw-product-list__column-product-name').to.have.text.that.equals(fixture.name);
    },
    'delete created product': (browser) => {
        const page = productPage(browser);


        browser
            .clickContextMenuItem('.sw-context-menu-item--danger', page.elements.contextMenuButton, `${page.elements.gridRow}--0`)
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
            .waitForElementVisible(page.elements.emptyState)
            .expect.element(page.elements.smartBarAmount).to.have.text.that.contains('(0)');
    },
    after: (browser) => {
        browser.end();
    }
};
