const fixture = {
    name: 'Beautiful Product'
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
    'edit product name via inline editing and verify edit': (browser) => {
        browser
            .waitForElementVisible('.sw-grid-row:first-child .sw-context-button__button')
            .moveToElement('.sw-grid-row:first-child', 5, 5).doubleClick()
            .fillField('input[name=sw-field--item-name]', 'Cyberdyne Systems T800', true)
            .waitForElementVisible('.sw-grid-row__inline-edit-action')
            .click('.sw-grid-row__inline-edit-action')
            .waitForElementNotPresent('.is--inline-editing ')
            .refresh()
            .waitForElementVisible('.sw-product-list__column-product-name')
            .assert.containsText('.sw-product-list__column-product-name', 'Cyberdyne Systems T800')
            .moveToElement('.sw-grid-row:last-child', 5, 5).doubleClick()
            .fillField('.is--inline-editing .sw-field__input input', 'Skynet Robotics T1000', true)
            .waitForElementVisible('.sw-grid-row__inline-edit-action')
            .click('.sw-grid-row__inline-edit-action')
            .waitForElementNotPresent('.is--inline-editing ')
            .assert.containsText('.sw-grid-row:last-child .sw-product-list__column-product-name', 'Skynet Robotics T1000');
    },
    after: (browser) => {
        browser.end();
    }
};
