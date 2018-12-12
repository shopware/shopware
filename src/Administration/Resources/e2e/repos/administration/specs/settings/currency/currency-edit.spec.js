module.exports = {
    '@tags': ['setting','currency-edit', 'currency', 'edit'],
    'open currency module': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/currency/index', 'Currencies');
    },
    'create new currency (Yen)': (browser) => {
        browser
            .click('a[href="#/sw/settings/currency/create"]')
            .waitForElementVisible('.sw-settings-currency-detail .sw-card__content')
            .assert.urlContains('#/sw/settings/currency/create')
            .assert.containsText('.sw-card__title', 'Settings')
            .fillField('input[name=sw-field--currency-name]', 'Yen')
            .fillField('input[name=sw-field--currency-shortName]', 'JPY')
            .fillField('input[name=sw-field--currency-symbol]', '¥')
            .fillField('input[name=sw-field--currency-factor]', '1.0076')
            .waitForElementPresent('.sw-settings-currency-detail__save-action')
            .click('.sw-settings-currency-detail__save-action')
            .checkNotification('Currency "Yen" has been saved successfully.')
            .assert.urlContains('#/sw/settings/currency/detail');
    },
    'go back to listing and verify creation': (browser) => {
        browser
            .click('a.smart-bar__back-btn')
            .waitForElementNotPresent('.sw-loader')
            .waitForElementVisible('.sw-settings-currency-list-grid')
            .waitForElementVisible('.sw-grid-row:last-child .sw-currency-list__column-name')
            .assert.containsText('.sw-grid-row:last-child .sw-currency-list__column-name', 'Yen');
    },
    'edit currency': (browser) => {
        browser
            .assert.containsText('.sw-grid-row:last-child .sw-currency-list__column-name', 'Yen')
            .clickContextMenuItem('.sw-currency-list__edit-action', '.sw-context-button__button','.sw-grid-row:last-child')
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
