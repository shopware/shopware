const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings', 'currency-create', 'currency', 'create'],
    'open currency module': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/currency/index', 'Currencies');
    },
    'create new currency (Yen)': (browser) => {
        const page = settingsPage(browser);

        browser
            .click('a[href="#/sw/settings/currency/create"]')
            .waitForElementVisible('.sw-settings-currency-detail .sw-card__content')
            .assert.urlContains('#/sw/settings/currency/create')
            .assert.containsText(page.elements.cardTitle, 'Settings')
            .fillField('input[name=sw-field--currency-name]', 'Yen')
            .fillField('input[name=sw-field--currency-shortName]', 'JPY')
            .fillField('input[name=sw-field--currency-symbol]', '¥')
            .fillField('input[name=sw-field--currency-factor]', '1.0076')
            .waitForElementPresent(page.elements.currencySaveAction)
            .click(page.elements.currencySaveAction)
            .checkNotification('Currency "Yen" has been saved successfully.')
            .assert.urlContains('#/sw/settings/currency/detail');
    },
    'go back to listing and verify creation': (browser) => {
        const page = settingsPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .waitForElementVisible('.sw-settings-currency-list-grid')
            .waitForElementNotPresent('.sw-alert__message')
            .waitForElementVisible(`${page.elements.gridRow}--3 ${page.elements.currencyColumnName}`)
            .assert.containsText(`${page.elements.gridRow}--3 ${page.elements.currencyColumnName}`, 'Yen');
    },
    after: (browser) => {
        browser.end();
    }
};
