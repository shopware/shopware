module.exports = {
    '@tags': ['setting','language-edit', 'language', 'edit'],
    'open language module': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/language/index', 'Languages');
    },
    'create new language': (browser) => {
        browser
            .click('a[href="#/sw/settings/language/create"]')
            .waitForElementVisible('.sw-settings-language-detail .sw-card__content')
            .assert.urlContains('#/sw/settings/language/create')
            .assert.containsText('.sw-card__title', 'Settings')
            .fillField('input[name=sw-field--language-name]', 'Philippine English english')
            .fillSwSelectComponent(
                '.sw-settings-language-detail__select-locale',
                {
                    value: 'English, Philippines (en_PH)',
                    searchTerm: 'en_PH'
                }
            )
            .fillSwSelectComponent(
                '.sw-settings-language-detail__select-parent',
                {
                    value: 'English',
                    searchTerm: 'English'
                }
            )
            .waitForElementPresent('.sw-settings-language-detail__save-action')
            .click('.sw-settings-language-detail__save-action')
            .checkNotification('Language "Philippine English english" has been saved successfully.')
            .assert.urlContains('#/sw/settings/language/detail');
    },
    'go back to listing and verify creation': (browser) => {
        browser
            .click('a.smart-bar__back-btn')
            .waitForElementNotPresent('.sw-loader')
            .waitForElementVisible('.sw-settings-language-list-grid')
            .waitForElementVisible('.sw-grid-row:last-child .sw-language-list__column-name')
            .assert.containsText('.sw-grid-row:last-child .sw-language-list__column-name', 'Philippine English english');
    },
    'edit language': (browser) => {
        browser
            .assert.containsText('.sw-grid-row:last-child .sw-language-list__column-name', 'Philippine English english')
            .clickContextMenuItem('.sw-language-list__edit-action', '.sw-context-button__button','.sw-grid-row:last-child')
            .waitForElementVisible('.sw-settings-language-detail .sw-card__content')
            .fillField('input[name=sw-field--language-name]', 'Very Philippine English english')
            .waitForElementPresent('.sw-settings-language-detail__save-action')
            .click('.sw-settings-language-detail__save-action')
            .checkNotification('Language "Very Philippine English english" has been saved successfully.')
            .assert.urlContains('#/sw/settings/language/detail');
    },
    'verify edited language': (browser) => {
        browser
            .click('a.smart-bar__back-btn')
            .waitForElementVisible('.sw-settings-language-list-grid')
            .waitForElementVisible('.sw-grid-row:last-child .sw-language-list__column-name')
            .assert.containsText('.sw-grid-row:last-child .sw-language-list__column-name', 'Very Philippine English english');
    },
    'delete language': (browser) => {
        browser
            .waitForElementVisible('.sw-grid-row:last-child .sw-language-list__column-name')
            .assert.containsText('.sw-grid-row:last-child .sw-language-list__column-name', 'Very Philippine English english')
            .clickContextMenuItem('.sw-context-menu-item--danger', '.sw-context-button__button','.sw-grid-row:last-child')
            .waitForElementVisible('.sw-modal')
            .assert.containsText('.sw-modal .sw-modal__body', 'Are you sure you want to delete the language "Very Philippine English english"?')
            .click('.sw-modal__footer button.sw-button--primary')
            .checkNotification('Language "Very Philippine English english" has been deleted successfully.');
    },
    after: (browser) => {
        browser.end();
    }
};
