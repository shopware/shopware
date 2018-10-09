module.exports = {
    '@tags': ['tax-edit', 'tax', 'edit'],
    'open tax module': (browser) => {
        browser
            .assert.containsText('.sw-settings .collapsible-text', 'Settings')
            .click('.sw-admin-menu__navigation-link[href="#/sw/settings/index"]')
            .waitForElementVisible('.sw-settings-item[href="#/sw/settings/tax/index"]')
            .click('.sw-settings-item[href="#/sw/settings/tax/index"]');
    },
    'goto create tax page': (browser) => {
        browser
            .click('a[href="#/sw/settings/tax/create"]')
            .waitForElementVisible('.sw-settings-tax-detail .sw-page__content')
            .assert.urlContains('#/sw/settings/tax/create')
            .assert.containsText('.sw-card__title', 'Settings')
            .setValue('input[name=sw-field--tax-name]', 'High tax')
            .setValue('input[name=sw-field--tax-taxRate]', '99')
            .click('.sw-settings-tax-detail__save-action')
            .waitForElementVisible('.sw-notifications .sw-alert')
            .assert.containsText('.sw-alert__message', 'The tax High tax has been saved successfully.')
            .assert.urlContains('#/sw/settings/tax/detail');
    },
    'go back to listing': (browser) => {
        browser
            .click('a.smart-bar__back-btn')
            .waitForElementVisible('.sw-settings-tax-list-grid')
            .click('.sw-alert button.sw-alert__close')
            .waitForElementNotPresent('.sw-alert__message');
    },
    'edit tax': (browser) => {
        browser
            .assert.containsText('.sw-grid-row:last-child .sw-tax-list__column-name', 'High tax')
            .click('.sw-grid-row:last-child .sw-context-button__button')
            .waitForElementPresent('body > .sw-context-menu')
            .click('body > .sw-context-menu .sw-tax-list__edit-action')
            .waitForElementVisible('.sw-settings-tax-detail .sw-card__content')
            .clearValue('input[name=sw-field--tax-name]')
            .setValue('input[name=sw-field--tax-name]', 'Even higher tax rate')
            .waitForElementPresent('.sw-settings-tax-detail__save-action')
            .click('.sw-settings-tax-detail__save-action')
            .waitForElementVisible('.sw-notifications .sw-alert')
            .assert.containsText('.sw-alert__message', 'The tax Even higher tax rate has been saved successfully.')
            .assert.urlContains('#/sw/settings/tax/detail');
    },
    'verify edited tax': (browser) => {
        browser
            .click('a.smart-bar__back-btn')
            .waitForElementVisible('.sw-settings-tax-list-grid')
            .click('.sw-alert button.sw-alert__close')
            .waitForElementNotPresent('.sw-alert__message')
            .waitForElementVisible('.sw-grid-row:last-child .sw-tax-list__column-name')
            .assert.containsText('.sw-grid-row:last-child .sw-tax-list__column-name', 'Even higher tax rate');
    },
    'delete tax': (browser) => {
        browser
            .assert.containsText('.sw-grid-row:last-child .sw-tax-list__column-name', 'Even higher tax rate')
            .click('.sw-grid-row:last-child .sw-context-button__button')
            .waitForElementPresent('body > .sw-context-menu')
            .click('body > .sw-context-menu .sw-context-menu-item--danger')
            .waitForElementVisible('.sw-modal')
            .assert.containsText('.sw-modal .sw-modal__body', 'Are you sure, you want to delete the tax Even higher tax rate?')
            .click('.sw-modal__footer button.sw-button--primary')
            .waitForElementVisible('.sw-notifications .sw-alert')
            .assert.containsText('.sw-notifications .sw-alert', 'The tax Even higher tax rate has been deleted successfully.');
    },
    after: (browser) => {
        browser.end();
    }
};
