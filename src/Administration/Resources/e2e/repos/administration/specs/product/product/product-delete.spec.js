let productFixture = global.FixtureService.loadJson('product.json');

module.exports = {
    '@tags': ['product-delete', 'product', 'delete'],
    before: (browser, done) => {
        productFixture.name = 'Soon be gone';
        productFixture.description = 'Came and went away so quickly';

        global.ProductFixtureService.setProductFixtures(productFixture, done);
    },
    'open product listing and look for the product to be deleted': (browser) => {
        browser
            .assert.containsText('.sw-admin-menu__navigation-list-item.sw-product span.collapsible-text', 'Products')
            .click('a.sw-admin-menu__navigation-link[href="#/sw/product/index"]')
            .waitForElementVisible('.smart-bar__actions a')
            .waitForElementVisible('.sw-page__smart-bar-amount')
            .assert.containsText('.sw-page__smart-bar-amount', '(1)')
            .waitForElementVisible('.sw-product-list__column-product-name')
            .assert.containsText('.sw-product-list__column-product-name', productFixture.name);
    },
    'delete created product': (browser) => {
        browser
            .clickContextMenuItem('.sw-context-menu-item--danger', '.sw-context-button__button', '.sw-grid-row:first-child')
            .waitForElementVisible('.sw-modal')
            .assert.containsText('.sw-modal .sw-product-list__confirm-delete-text', `Are you sure you really want to delete the product "${productFixture.name}"?`)
            .click('.sw-modal__footer button.sw-button--primary')
            .waitForElementNotPresent('.sw-modal')
            .waitForElementNotPresent('.sw-product-list__column-product-name > a')
            .waitForElementVisible('.sw-empty-state')
            .waitForElementVisible('.sw-page__smart-bar-amount')
            .assert.containsText('.sw-page__smart-bar-amount', '(0)');
    },
    'search for deleted product and expecting no result': (browser) => {
        browser
            .fillGlobalSearchField('Soon be gone')
            .waitForElementVisible('.sw-empty-state')
            .waitForElementVisible('.sw-page__smart-bar-amount')
            .assert.containsText('.sw-page__smart-bar-amount', '(0)');
    },
    after: (browser) => {
        browser.end();
    }
};
