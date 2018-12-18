const currencyFixture = global.FixtureService.loadJson('currency.json');

module.exports = {
    '@tags': ['setting', 'currency-edit', 'currency', 'edit'],
    before: (browser, done) => {
        global.FixtureService.create('/v1/currency', currencyFixture, 'currency', done);
    },
    'open currency module and look for currency to be edited': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/currency/index', 'Currencies')
            .waitForElementVisible('.sw-settings-currency-list-grid')
            .waitForElementVisible('.sw-grid-row:last-child .sw-currency-list__column-name')
            .assert.containsText('.sw-grid-row:last-child .sw-currency-list__column-name', currencyFixture.name);
    },
    'edit currency': (browser) => {
        browser
            .assert.containsText('.sw-grid-row:last-child .sw-currency-list__column-name', currencyFixture.name)
            .clickContextMenuItem('.sw-currency-list__edit-action', '.sw-context-button__button', '.sw-grid-row:last-child')
            .waitForElementVisible('.sw-settings-currency-detail .sw-card__content')
            .clearValue('input[name=sw-field--currency-name]')
            .setValue('input[name=sw-field--currency-name]', 'Yen but true')
            .waitForElementPresent('.sw-settings-currency-detail__save-action')
            .click('.sw-settings-currency-detail__save-action')
            .checkNotification('Currency "Yen but true" has been saved successfully.')
            .assert.urlContains('#/sw/settings/currency/detail');
    },
    'verify edited currency': (browser) => {
        browser
            .click('a.smart-bar__back-btn')
            .waitForElementVisible('.sw-settings-currency-list-grid')
            .waitForElementVisible('.sw-grid-row:last-child .sw-currency-list__column-name')
            .assert.containsText('.sw-grid-row:last-child .sw-currency-list__column-name', 'Yen but true');
    },
    after: (browser) => {
        browser.end();
    }
};
