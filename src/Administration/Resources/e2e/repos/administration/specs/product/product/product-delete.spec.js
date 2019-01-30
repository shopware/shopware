const productPage = require('administration/page-objects/module/sw-product.page-object.js');

const fixture = {
    name: 'Soon be gone'
};

module.exports = {
    '@tags': ['product-delete', 'product', 'delete'],
    before: (browser, done) => {
        global.ProductFixtureService.setProductFixtures(fixture).then(() => {
            done();
        });
    },
    'open product listing and look for the product to be deleted': (browser) => {
        const page = productPage(browser);

        browser
            .assert.containsText('.sw-admin-menu__navigation-list-item.sw-product span.collapsible-text', 'Products')
            .click('a.sw-admin-menu__navigation-link[href="#/sw/product/index"]')
            .waitForElementVisible(page.elements.smartBarAmount)
            .assert.containsText(page.elements.smartBarAmount, '(1)')
            .waitForElementVisible('.sw-product-list__column-product-name')
            .assert.containsText('.sw-product-list__column-product-name', fixture.name);
    },
    'delete created product': (browser) => {
        const page = productPage(browser);


        browser
            .clickContextMenuItem('.sw-context-menu-item--danger', page.elements.contextMenuButton, `${page.elements.gridRow}--0`)
            .waitForElementVisible(page.elements.modal)
            .assert.containsText(`${page.elements.modal} .sw-product-list__confirm-delete-text`, `Are you sure you really want to delete the product "${fixture.name}"?`)
            .click(`${page.elements.modal}__footer button${page.elements.primaryButton}`)
            .waitForElementNotPresent(page.elements.modal)
            .waitForElementNotPresent('.sw-product-list__column-product-name > a')
            .waitForElementVisible(page.elements.emptyState)
            .waitForElementVisible(page.elements.smartBarAmount)
            .assert.containsText(page.elements.smartBarAmount, '(0)');
    },
    'search for deleted product and expecting no result': (browser) => {
        const page = productPage(browser);

        browser
            .fillGlobalSearchField('Soon be gone')
            .waitForElementVisible(page.elements.emptyState)
            .waitForElementVisible(page.elements.smartBarAmount)
            .assert.containsText(page.elements.smartBarAmount, '(0)');
    },
    after: (browser) => {
        browser.end();
    }
};
