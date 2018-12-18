const currencyFixture = global.FixtureService.loadJson('currency.json');

module.exports = {
    '@tags': ['setting', 'currency-delete', 'currency', 'delete'],
    before: (browser, done) => {
        global.FixtureService.create('/v1/currency', currencyFixture, 'currency', done);
    },
    'open currency module and look for currency to be deleted': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/currency/index', 'Currencies')
            .waitForElementVisible('.sw-grid-row:last-child .sw-currency-list__column-name')
            .assert.containsText('.sw-grid-row:last-child .sw-currency-list__column-name', currencyFixture.name);
    },
    'delete currency': (browser) => {
        browser
            .clickContextMenuItem('.sw-context-menu-item--danger', '.sw-context-button__button', '.sw-grid-row:last-child')
            .waitForElementVisible('.sw-modal')
            .assert.containsText('.sw-modal .sw-modal__body', `Are you sure you want to delete the currency "${currencyFixture.name}"?`)
            .click('.sw-modal__footer button.sw-button--primary')
            .checkNotification(`Currency "${currencyFixture.name}" has been deleted successfully.`);
    },
    after: (browser) => {
        browser.end();
    }
};
