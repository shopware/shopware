module.exports = {
    '@tags': ['product', 'product-edit', 'edit'],
    before: (browser, done) => {
        global.ProductFixtureService.setProductFixtures({
            name: "Really good product",
            description: "This describes a product. It is your product. You will take care of your product. You will set a price, keep records of storage quantities and take care of whatever needs your product might develop. You love your product. Your are the product. Now go find someone dumb enough to buy your precious product.",
        }).then(() => {
            done();
        });
    },
    'open product listing': (browser) => {
        browser
            .openMainMenuEntry('#/sw/product/index', 'Products')
            .waitForElementVisible('.smart-bar__actions a')
            .waitForElementVisible('.sw-page__smart-bar-amount')
            .assert.containsText('.sw-page__smart-bar-amount', '(1)');
    },
    'edit product': (browser) => {
        browser
            .waitForElementVisible('.sw-grid-row:first-child .sw-context-button__button')
            .click('.sw-grid-row:first-child .sw-context-button__button')
            .waitForElementVisible('.sw-context-menu')
            .click('.sw-context-menu .sw-context-menu-item__text')
            .waitForElementNotPresent('.sw-loader')
            .fillField('input[name=sw-field--product-name]', 'Geändertes, immernoch supergeiles Produkt', true)
            .fillField('.ql-editor', 'Cant you see this is a great product?', true, 'editor')
            .click('.sw-product-detail__save-action')
            .checkNotification('Product "Geändertes, immernoch supergeiles Produkt" has been saved successfully.')
            .click('.smart-bar__actions .sw-button__content')
            .waitForElementVisible('.sw-page__smart-bar-amount')
            .assert.containsText('.sw-page__smart-bar-amount', '(1)');
},
    after: (browser) => {
        browser.end();
    }
};
