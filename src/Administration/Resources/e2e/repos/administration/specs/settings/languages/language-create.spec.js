const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings', 'language-create', 'language', 'create'],
    'open language module': (browser) => {
        browser
            .openMainMenuEntry({
                mainMenuPath: '#/sw/settings/index',
                menuTitle: 'Settings',
                index: 6,
                subMenuItemPath: '#/sw/settings/language/index',
                subMenuTitle: 'Languages'
            });
    },
    'create new language': (browser) => {
        const page = settingsPage(browser);

        browser
            .click('a[href="#/sw/settings/language/create"]')
            .waitForElementVisible('.sw-settings-language-detail .sw-card__content')
            .assert.urlContains('#/sw/settings/language/create')
            .assert.containsText(page.elements.cardTitle, 'Settings')
            .fillField('input[name=sw-field--language-name]', 'Philippine English')
            .fillSwSelectComponent(
                '.sw-settings-language-detail__select-locale',
                {
                    value: 'English, Philippines',
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
            .waitForElementPresent(page.elements.languageSaveAction)
            .click(page.elements.languageSaveAction)
            .checkNotification('Language "Philippine English" has been saved successfully.')
            .assert.urlContains('#/sw/settings/language/detail');
    },
    'go back to listing and verify creation': (browser) => {
        const page = settingsPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .waitForElementVisible('.sw-settings-language-list-grid')
            .waitForElementVisible(`${page.elements.gridRow}--2 ${page.elements.languageColumnName}`)
            .assert.containsText(`${page.elements.gridRow}--2 ${page.elements.languageColumnName}`, 'Philippine English');
    },
    after: (browser) => {
        browser.end();
    }
};
