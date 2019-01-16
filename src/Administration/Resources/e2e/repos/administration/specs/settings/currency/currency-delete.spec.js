module.exports = {
    '@tags': ['setting', 'currency-delete', 'currency', 'delete'],
    before: (browser, done) => {
        global.FixtureService.create('currency').then(() => {
            done();
        });
    },
    'open currency module and look for currency to be deleted': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/currency/index', 'Currencies')
            .waitForElementVisible('.sw-grid-row:last-child .sw-currency-list__column-name')
            .assert.containsText('.sw-grid-row:last-child .sw-currency-list__column-name', global.FixtureService.basicFixture.name);
    },
    'delete currency': (browser) => {
        browser
            .clickContextMenuItem('.sw-context-menu-item--danger', '.sw-context-button__button', '.sw-grid-row:last-child')
            .waitForElementVisible('.sw-modal')
            .assert.containsText('.sw-modal .sw-modal__body', `Are you sure you want to delete the currency "${global.FixtureService.basicFixture.name}"?`)
            .click('.sw-modal__footer button.sw-button--primary')
            .checkNotification(`Currency "${global.FixtureService.basicFixture.name}" has been deleted successfully.`);
    },
    after: (browser) => {
        browser.end();
    }
};
