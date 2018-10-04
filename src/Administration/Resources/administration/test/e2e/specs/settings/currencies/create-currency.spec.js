module.exports = {
    '@tags': ['currency-create', 'currency','create'],
    'open currency module': (browser) => {
        browser
            .assert.containsText('.sw-settings .collapsible-text', 'Settings')
            .click('.sw-admin-menu__navigation-link[href="#/sw/settings/index"]')
            .waitForElementVisible('.sw-settings-item[href="#/sw/settings/currency/index"]')
            .click('.sw-settings-item[href="#/sw/settings/currency/index"]');
    },
    'create new currency (Yen)': (browser) => {
        browser
            .click('a[href="#/sw/settings/currency/create"]')
            .waitForElementVisible('.sw-settings-currency-detail .sw-card__content')
            .assert.urlContains('#/sw/settings/currency/create')
            .assert.containsText('.sw-card__title', 'Settings')
            .setValue('input[name=sw-field--currency-name]', 'Yen')
            .setValue('input[name=sw-field--currency-shortName]', 'Yen')
            .setValue('input[name=sw-field--currency-symbol]', '¥')
            .setValue('input[name=sw-field--currency-factor]', '0.0076')
            .waitForElementPresent('.sw_settings_currency_detail__save-action')
            .click('.sw_settings_currency_detail__save-action')
            .waitForElementVisible('.sw-notifications .sw-alert')
            .assert.containsText('.sw-alert__message', 'The currency Yen has been saved successfully.')
            .assert.urlContains('#/sw/settings/currency/detail');
    },
    'go back to listing and verify creation': (browser) => {
        browser
            .click('a.smart-bar__back-btn')
            .waitForElementVisible('.sw-settings-currency-list-grid')
            .click('.sw-alert button.sw-alert__close')
            .waitForElementNotPresent('.sw-alert__message')
            .waitForElementVisible('.sw-grid-row:last-child .sw-currency-list__column-currency-name')
            .assert.containsText('.sw-grid-row:last-child .sw-currency-list__column-currency-name', 'Yen');
    },
    'delete currency': (browser) => {
        browser
            .assert.containsText('.sw-grid-row:last-child .sw-currency-list__column-currency-name', 'Yen')
            .click('.sw-grid-row:last-child .sw-context-button__button')
            .waitForElementPresent('body > .sw-context-menu')
            .click('body > .sw-context-menu .sw-context-menu-item--danger')
            .waitForElementVisible('.sw-modal')
            .assert.containsText('.sw-modal .sw-modal__body', 'Are you sure, you want to delete the currency Yen?')
            .click('.sw-modal__footer button.sw-button--primary')
            .waitForElementVisible('.sw-notifications .sw-alert')
            .assert.containsText('.sw-notifications .sw-alert', 'The currency Yen has been deleted successfully.');
    },
    after: (browser) => {
        browser.end();
    }
};
