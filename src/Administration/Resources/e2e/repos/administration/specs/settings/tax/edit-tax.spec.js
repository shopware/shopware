module.exports = {
    '@tags': ['setting','tax-edit', 'tax', 'edit'],
    'open tax module': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/tax/index', 'Tax');
    },
    'goto create tax page': (browser) => {
        browser
            .click('a[href="#/sw/settings/tax/create"]')
            .waitForElementVisible('.sw-settings-tax-detail .sw-page__content')
            .assert.urlContains('#/sw/settings/tax/create')
            .assert.containsText('.sw-card__title', 'Settings')
            .fillField('input[name=sw-field--tax-name]', 'High tax')
            .fillField('input[name=sw-field--tax-taxRate]', '99')
            .click('.sw-settings-tax-detail__save-action')
            .waitForElementVisible('.sw-notifications .sw-alert')
            .assert.containsText('.sw-alert__message', 'Tax "High tax" has been saved successfully.')
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
            .clickContextMenuItem('.sw-tax-list__edit-action', '.sw-context-button__button','.sw-grid-row:last-child')
            .waitForElementVisible('.sw-settings-tax-detail .sw-card__content')
            .fillField('input[name=sw-field--tax-name]', 'Even higher tax rate')
            .waitForElementPresent('.sw-settings-tax-detail__save-action')
            .click('.sw-settings-tax-detail__save-action')
            .waitForElementVisible('.sw-notifications .sw-alert')
            .assert.containsText('.sw-alert__message', 'Tax "Even higher tax rate" has been saved successfully.')
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
            .waitForElementVisible('.sw-grid-row:last-child .sw-tax-list__column-name')
            .assert.containsText('.sw-grid-row:last-child .sw-tax-list__column-name', 'Even higher tax rate')
            .clickContextMenuItem('.sw-context-menu-item--danger', '.sw-context-button__button','.sw-grid-row:last-child')
            .waitForElementVisible('.sw-modal')
            .assert.containsText('.sw-modal .sw-modal__body', 'Are you sure you want to delete the tax "Even higher tax rate"?')
            .click('.sw-modal__footer button.sw-button--primary')
            .waitForElementVisible('.sw-notifications .sw-alert')
            .assert.containsText('.sw-notifications .sw-alert', 'Tax "Even higher tax rate" has been deleted successfully.');
    },
    after: (browser) => {
        browser.end();
    }
};
