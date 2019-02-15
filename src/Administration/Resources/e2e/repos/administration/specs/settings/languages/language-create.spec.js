const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings', 'language-create', 'language', 'create'],
    'open language module': (browser) => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/language/index',
                mainMenuId: 'sw-settings',
                subMenuId: 'sw-settings-language'
            });
    },
    'create new language': (browser) => {
        const page = settingsPage(browser);

        browser
            .click('a[href="#/sw/settings/language/create"]')
            .expect.element(page.elements.cardTitle).to.have.text.that.contains('Settings');

        browser
            .assert.urlContains('#/sw/settings/language/create')
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
            .expect.element(`${page.elements.gridRow}--2 ${page.elements.languageColumnName}`).to.have.text.that.contains('Philippine English');
    },
    after: (browser) => {
        browser.end();
    }
};
