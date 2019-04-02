const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings', 'currency-edit', 'currency', 'edit'],
    before: (browser, done) => {
        global.AdminFixtureService.create('currency').then(() => {
            done();
        });
    },
    'open currency module and look for currency to be edited': (browser) => {
        const page = settingsPage(browser);

        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-settings-currency')
            .assert.urlContains('#/sw/settings/currency/index')
            .waitForElementVisible('.sw-settings-currency-list-grid')
            .waitForElementVisible(`${page.elements.gridRow}--3 ${page.elements.currencyColumnName}`)
            .assert.containsText(`${page.elements.gridRow}--3 ${page.elements.currencyColumnName}`, global.AdminFixtureService.basicFixture.name);
    },
    'edit currency': (browser) => {
        const page = settingsPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-currency-list__edit-action',
                scope: `${page.elements.gridRow}--3`
            })
            .waitForElementVisible('.sw-settings-currency-detail .sw-card__content')
            .clearValue('input[name=sw-field--currency-name]')
            .fillField('input[name=sw-field--currency-name]', 'Yen but true', true)
            .click(page.elements.currencySaveAction)
            .checkNotification('Currency "Yen but true" has been saved successfully.')
            .assert.urlContains('#/sw/settings/currency/detail');
    },
    'verify edited currency': (browser) => {
        const page = settingsPage(browser);

        browser
            .click('a.smart-bar__back-btn')
            .expect.element(`${page.elements.gridRow}--3 ${page.elements.currencyColumnName}`).to.have.text.that.contains('Yen but true');
    }
};
