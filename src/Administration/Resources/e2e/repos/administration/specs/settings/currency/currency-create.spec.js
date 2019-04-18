const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings', 'currency-create', 'currency', 'create'],
    'open currency module': (browser) => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-settings-currency')
            .assert.urlContains('#/sw/settings/currency/index');
    },
    'create new currency (Yen)': (browser) => {
        const page = settingsPage(browser);

        browser
            .click('a[href="#/sw/settings/currency/create"]')
            .waitForElementVisible('.sw-settings-currency-detail .sw-card__content')
            .assert.urlContains('#/sw/settings/currency/create')
            .fillField('input[name=sw-field--currency-name]', 'Yen')
            .fillField('input[name=sw-field--currency-shortName]', 'JPY')
            .fillField('input[name=sw-field--currency-symbol]', '¥')
            .fillField('input[name=sw-field--currency-decimalPrecision]', '2')
            .fillField('input[name=sw-field--currency-factor]', '1.0076')
            .click(page.elements.currencySaveAction)
            .checkNotification('Currency "Yen" has been saved successfully.')
            .assert.urlContains('#/sw/settings/currency/detail');
    },
    'go back to listing and verify creation': (browser) => {
        const page = settingsPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .waitForElementNotPresent('.sw-alert__message')
            .expect.element(`${page.elements.gridRow}--3 ${page.elements.currencyColumnName}`).to.have.text.that.contains('Yen');
    }
};
