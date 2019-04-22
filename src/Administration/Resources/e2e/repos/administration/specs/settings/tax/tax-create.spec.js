const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings', 'tax-create', 'tax', 'create'],
    'open tax module': (browser) => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-settings-tax')
            .assert.urlContains('#/sw/settings/tax/index');
    },
    'goto create tax page': (browser) => {
        const page = settingsPage(browser);

        browser
            .click('a[href="#/sw/settings/tax/create"]')
            .waitForElementVisible('.sw-settings-tax-detail .sw-page__content')
            .assert.urlContains('#/sw/settings/tax/create')
            .fillField('input[name=sw-field--tax-name]', 'High tax')
            .fillField('input[name=sw-field--tax-taxRate]', '99')
            .click(page.elements.taxSaveAction)
            .checkNotification('Tax "High tax" has been saved successfully.')
            .assert.urlContains('#/sw/settings/tax/detail');
    },
    'go back to listing and verify tax': (browser) => {
        const page = settingsPage(browser);

        browser
            .click('a.smart-bar__back-btn')
            .expect.element(`${page.elements.gridRow}--5 ${page.elements.taxColumnName}`).to.have.text.that.equals('High tax');
    }
};
