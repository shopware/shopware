const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings', 'language-edit', 'language', 'edit'],
    before: (browser, done) => {
        global.LanguageFixtureService.setLanguageFixtures().then(() => {
            done();
        });
    },
    'open language module and look for language to be edited': (browser) => {
        const page = settingsPage(browser);

        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-settings-language')
            .assert.urlContains('#/sw/settings/language/index')
            .expect.element(`${page.elements.gridRow}--2 ${page.elements.languageColumnName}`).to.have.text.that.contains(global.LanguageFixtureService.getLanguageName());
    },
    'edit language': (browser) => {
        const page = settingsPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-language-list__edit-action',
                scope: `${page.elements.gridRow}--2`
            })
            .waitForElementVisible('.sw-settings-language-detail .sw-card__content')
            .fillField('input[name=sw-field--language-name]', 'Very Philippine English', true)
            .click(page.elements.languageSaveAction)
            .checkNotification('Language "Very Philippine English" has been saved successfully.')
            .assert.urlContains('#/sw/settings/language/detail');
    },
    'verify edited language': (browser) => {
        const page = settingsPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .expect.element(`${page.elements.gridRow}--2 ${page.elements.languageColumnName}`).to.have.text.that.contains('Very Philippine English');
    }
};
