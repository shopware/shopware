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
                mainMenuPath: '#/sw/settings/index',
                menuTitle: 'Settings',
                index: 5,
                subMenuItemPath: '#/sw/settings/language/index',
                subMenuTitle: 'Languages'
            })
            .expect.element(`${page.elements.gridRow}--2 ${page.elements.languageColumnName}`).to.have.text.that.contains(global.LanguageFixtureService.languageFixture.name);

    },
    'edit language': (browser) => {
        const page = settingsPage(browser);

        browser
            .clickContextMenuItem('.sw-language-list__edit-action', page.elements.contextMenuButton, `${page.elements.gridRow}--2`)
            .waitForElementVisible('.sw-settings-language-detail .sw-card__content')
            .fillField('input[name=sw-field--language-name]', 'Very Philippine English', true)
            .waitForElementPresent(page.elements.languageSaveAction)
            .click(page.elements.languageSaveAction)
            .checkNotification('Language "Very Philippine English" has been saved successfully.')
            .assert.urlContains('#/sw/settings/language/detail');
    },
    'verify edited language': (browser) => {
        const page = settingsPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .expect.element(`${page.elements.gridRow}--2 ${page.elements.languageColumnName}`).to.have.text.that.contains('Very Philippine English');
    },
    after: (browser) => {
        browser.end();
    }
};
