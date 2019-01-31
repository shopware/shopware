module.exports = {
    '@tags': ['product-stream-inline-edit', 'product-stream', 'inline-edit'],
    '@disabled': !global.flags.isActive('next739'),
    before: (browser, done) => {
        global.FixtureService.create('product-stream').then(() => {
            done();
        });
    },
    'navigate to product stream module': (browser) => {
        browser
            .openMainMenuEntry('#/sw/product/index', 'Product', '#/sw/product/stream/index', 'Product streams')
            .assert.urlContains('#/sw/product/stream/index')
            .waitForElementVisible('.sw-button__content');
    },
    'inline edit product stream name and description and verify edits': (browser) => {
        browser
            .waitForElementVisible('.sw-grid-row:first-child .sw-context-button__button')
            .expect.element('.sw-grid-row:first-child .sw-product-stream-list__column-name').to.have.text.that.equals('1st product stream');

        browser
            .moveToElement('.sw-grid-row:first-child', 5, 5).doubleClick()
            .waitForElementVisible('.is--inline-editing')
            .fillField('input[name=sw-field--item-name]', 'Stream it', true)
            .waitForElementVisible('.sw-grid-row__inline-edit-action')
            .click('.sw-grid-row__inline-edit-action')
            .waitForElementNotPresent('.is--inline-editing ')
            .refresh()
            .waitForElementVisible('.sw-page__smart-bar-amount')
            .assert.containsText('.sw-grid-column.sw-grid__cell.sw-grid-column--left', 'Stream it')
            .moveToElement('.sw-grid-row:first-child .sw-grid-column:nth-child(3)', 5, 5).doubleClick()
            .waitForElementVisible('.sw-grid-row__inline-edit-action')
            .fillField('input[name=sw-field--item-description]', 'Edit the first stream', true)
            .click('.sw-grid-row__inline-edit-action')
            .waitForElementNotPresent('.is--inline-editing ')
            .assert.containsText('.sw-grid-column.sw-grid__cell.sw-grid-column--left:nth-child(2)', 'Stream it');
    },
    after: (browser) => {
        browser.end();
    }
};
