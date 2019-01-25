const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['setting','tax-create', 'tax', 'create'],
    'open tax module': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/tax/index', 'Tax');
    },
    'goto create tax page': (browser) => {
        const page = settingsPage(browser);

        browser
            .click('a[href="#/sw/settings/tax/create"]')
            .waitForElementVisible('.sw-settings-tax-detail .sw-page__content')
            .assert.urlContains('#/sw/settings/tax/create')
            .assert.containsText('.sw-card__title', 'Settings')
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
            .waitForElementVisible('.sw-settings-tax-list-grid')
            .waitForElementVisible(`${page.elements.gridRow}:last-child ${page.elements.taxColumnName}`)
            .assert.containsText(`${page.elements.gridRow}:last-child ${page.elements.taxColumnName}`, 'High tax');
    },
    after: (browser) => {
        browser.end();
    }
};
