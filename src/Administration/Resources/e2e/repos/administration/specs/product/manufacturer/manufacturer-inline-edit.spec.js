module.exports = {
    '@tags': ['manufacturer-inline-edit', 'manufacturer', 'inline-edit'],
    before: (browser, done) => {
        global.FixtureService.create('product-manufacturer').then(() => {
            done();
        });
    },
    'navigate to manufacturer module': (browser) => {
        browser
            .openMainMenuEntry('#/sw/product/index', 'Product', '#/sw/manufacturer/index', 'Manufacturer')
            .assert.urlContains('#/sw/manufacturer/index')
            .waitForElementVisible('.sw-button__content');
    },
    'inline edit manufacturer name and website and verify edits': (browser) => {
        browser
            .waitForElementVisible('.sw-grid-row:first-child .sw-context-button__button')
            .moveToElement('.sw-grid-row:first-child', 5, 5).doubleClick()
            .waitForElementVisible('.sw-grid-row__inline-edit-action')
            .fillField('input[name=sw-field--item-name]', 'I am Groot', true)
            .waitForElementVisible('.sw-grid-row__inline-edit-action')
            .click('.sw-grid-row__inline-edit-action')
            .waitForElementNotPresent('.is--inline-editing ')
            .refresh()
            .waitForElementVisible('.sw-page__smart-bar-amount')
            .assert.containsText('.sw-grid-column.sw-grid__cell.sw-grid-column--left', 'I am Groot')
            .moveToElement('.sw-grid-row:first-child .sw-grid-column:nth-child(3)', 5, 5).doubleClick()
            .waitForElementVisible('.sw-grid-row__inline-edit-action')
            .fillField('input[name=sw-field--item-link]', 'www.google.ru', true)
            .click('.sw-grid-row__inline-edit-action')
            .waitForElementNotPresent('.is--inline-editing ')
            .assert.containsText('.sw-grid-column.sw-grid__cell.sw-grid-column--left:nth-child(2)', 'I am Groot');
    },
    after: (browser) => {
        browser.end();
    }
};
