module.exports = {
    '@tags': ['setting','tax-delete', 'tax', 'delete'],
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
            .checkNotification('Tax "High tax" has been saved successfully.')
            .assert.urlContains('#/sw/settings/tax/detail');
    },
    'go back to listing': (browser) => {
        browser
            .click('a.smart-bar__back-btn')
            .waitForElementVisible('.sw-settings-tax-list-grid');
    },
    'delete tax': (browser) => {
        browser
            .waitForElementVisible('.sw-grid-row:last-child .sw-tax-list__column-name')
            .assert.containsText('.sw-grid-row:last-child .sw-tax-list__column-name', 'High tax')
            .clickContextMenuItem('.sw-context-menu-item--danger', '.sw-context-button__button','.sw-grid-row:last-child')
            .waitForElementVisible('.sw-modal')
            .assert.containsText('.sw-modal .sw-modal__body', 'Are you sure you want to delete the tax "High tax"?')
            .click('.sw-modal__footer button.sw-button--primary')
            .checkNotification('Tax "High tax" has been deleted successfully.');
    },
    after: (browser) => {
        browser.end();
    }
};
