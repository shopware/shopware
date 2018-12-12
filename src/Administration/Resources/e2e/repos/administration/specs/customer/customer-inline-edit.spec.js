module.exports = {
    '@tags': ['customer-inline-edit', 'customer', 'inline-edit'],
    before: (browser, done) => {
        global.CustomerFixtureService.setCustomerFixture().then(() => {
            done();
        });
    },
    'open customer listing': (browser) => {
        browser
            .openMainMenuEntry('#/sw/customer/index', 'Customers')
            .waitForElementVisible('.smart-bar__actions a')
            .waitForElementVisible('.sw-page__smart-bar-amount')
            .assert.containsText('.sw-page__smart-bar-amount', '(1)');
    },
    'edit customer email via inline editing and verify edits': (browser) => {
        browser
            .waitForElementVisible('.sw-grid-row .sw-context-button__button')
            .moveToElement('.sw-grid-row:first-child', 5, 5).doubleClick()
            .waitForElementPresent('.is--inline-editing ')
            .fillField('input[name=sw-field--item-firstName]', 'Meghan', true)
            .fillField('input[name=sw-field--item-lastName]', 'Markle', true)
            .waitForElementVisible('.sw-grid-row__inline-edit-action')
            .click('.sw-grid-row__inline-edit-action')
            .waitForElementNotPresent('.is--inline-editing ')
            .refresh()
            .waitForElementVisible('.sw-grid-row .sw-context-button__button')
            .assert.containsText('.sw-customer-list__column-customer-name', 'Meghan Markle')
            .moveToElement('.sw-grid-row:first-child', 5, 5).doubleClick()
            .waitForElementVisible('.sw-grid-row__inline-edit-action')
            .fillField('.is--inline-editing .sw-field__input input', '007-JB-337826', true)
            .click('.sw-grid-row__inline-edit-action')
            .waitForElementNotPresent('.is--inline-editing ')
            .assert.containsText('.sw-grid-column.sw-grid__cell.sw-grid-column--right', '007-JB-337826');
    },
    after: (browser) => {
        browser.end();
    }
};