const fixture = {
    name: 'First one'
};

module.exports = {
    '@tags': ['product', 'product-inline-edit', 'inline-edit'],
    before: (browser, done) => {
        global.ProductFixtureService.setProductFixtures(fixture).then(() => {
            done();
        });
    },
    'open product listing and look for the product to be edited': (browser) => {
        browser
            .openMainMenuEntry('#/sw/product/index', 'Products')
            .waitForElementVisible('.smart-bar__actions a')
            .waitForElementVisible('.sw-page__smart-bar-amount')
            .assert.containsText('.sw-page__smart-bar-amount', '(1)')
            .waitForElementVisible('.sw-grid-row:first-child .sw-context-button__button')
            .assert.containsText('.sw-product-list__column-product-name', fixture.name);
    },
    'edit product name via inline editing and verify change': (browser) => {
        browser
            .moveToElement('.sw-grid-row:first-child', 5, 5).doubleClick()
            .fillField('input[name=sw-field--item-name]', 'Second one', 'input', true)
            .waitForElementVisible('.is--inline-editing .sw-button--primary')
            .click('.is--inline-editing .sw-button--primary')
            .waitForElementNotPresent('.is--inline-editing ')
            .refresh()
            .waitForElementVisible('.sw-product-list__column-product-name')
            .assert.containsText('.sw-product-list__column-product-name', 'Second one');
    },
    after: (browser) => {
        browser.end();
    }

};
