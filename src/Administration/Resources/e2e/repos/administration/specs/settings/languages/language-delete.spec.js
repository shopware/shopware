module.exports = {
    '@tags': ['setting', 'language-delete', 'language', 'delete'],
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
            .fillField('input[name=sw-field--language-name]', 'Philippine English')
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
            .checkNotification('Language "Philippine English" has been saved successfully.')
            .assert.urlContains('#/sw/settings/language/detail');
    },
    'delete language': (browser) => {
        browser
            .click('a.smart-bar__back-btn')
            .waitForElementVisible('.sw-grid-row:last-child .sw-language-list__column-name')
            .assert.containsText('.sw-grid-row:last-child .sw-language-list__column-name', 'Philippine English')
            .clickContextMenuItem('.sw-context-menu-item--danger', '.sw-context-button__button', '.sw-grid-row:last-child')
            .waitForElementVisible('.sw-modal')
            .assert.containsText(
                '.sw-modal .sw-modal__body',
                'Are you sure you want to delete the language "Philippine English"?'
            )
            .click('.sw-modal__footer button.sw-button--primary')
            .checkNotification('Language "Philippine English" has been deleted successfully.');
    },
    after: (browser) => {
        browser.end();
    }
};
