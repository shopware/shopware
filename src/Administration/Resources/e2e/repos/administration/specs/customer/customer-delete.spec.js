module.exports = {
    '@tags': ['customer-delete', 'customer', 'delete'],
    before: (browser, done) => {
        global.CustomerFixtureService.setCustomerFixture({
            email: 'test-again@example.com'
        }).then(() => {
            done();
        });
    },
    'open customer listing': (browser) => {
        browser
            .openMainMenuEntry('#/sw/customer/index', 'Customers')
            .waitForElementVisible('.sw-page__smart-bar-amount')
            .assert.containsText('.sw-page__smart-bar-amount', '(1)')
            .waitForElementVisible('.sw-customer-list__content');
    },
    'delete customer and verify deletion': (browser) => {
        browser
            .waitForElementPresent('.sw-customer-list__column-customer-name')
            .assert.containsText('.sw-customer-list__column-customer-name', 'Pep Eroni')
            .clickContextMenuItem('.sw-context-menu-item--danger', '.sw-context-button__button', '.sw-grid-row:first-child')
            .waitForElementVisible('.sw-modal')
            .assert.containsText('.sw-modal .sw-customer-list__confirm-delete-text', 'Are you sure you want to delete the customer "Pep Eroni"?')
            .click('.sw-modal__footer button.sw-button--primary')
            .waitForElementNotPresent('.sw-customer-list__column-customer-name')
            .waitForElementNotPresent('.sw-modal')
            .waitForElementPresent('.sw-empty-state__title')
            .assert.containsText('.sw-page__smart-bar-amount', '(0)');
    },
    after: (browser) => {
        browser.end();
    }
};