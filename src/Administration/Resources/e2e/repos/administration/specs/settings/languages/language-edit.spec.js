const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['setting','language-edit', 'language', 'edit'],
    before: (browser, done) => {
        global.LanguageFixtureService.setLanguageFixtures().then(() => {
            done();
        });
    },
    'open language module and look for language to be edited': (browser) => {
        const page = settingsPage(browser);

        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/language/index', 'Languages')
            .waitForElementVisible('.sw-settings-language-list-grid')
            .waitForElementVisible(`${page.elements.gridRow}:last-child ${page.elements.languageColumnName}`)
            .assert.containsText(`${page.elements.gridRow}:last-child ${page.elements.languageColumnName}`, global.LanguageFixtureService.languageFixture.name);
    },
    'edit language': (browser) => {
        const page = settingsPage(browser);

        browser
            .assert.containsText(`${page.elements.gridRow}:last-child ${page.elements.languageColumnName}`, 'Philippine English')
            .clickContextMenuItem('.sw-language-list__edit-action', page.elements.contextMenuButton,`${page.elements.gridRow}:last-child`)
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
            .waitForElementVisible('.sw-settings-language-list-grid')
            .waitForElementVisible(`${page.elements.gridRow}:last-child ${page.elements.languageColumnName}`)
            .assert.containsText(`${page.elements.gridRow}:last-child ${page.elements.languageColumnName}`, 'Very Philippine English');
    },
    after: (browser) => {
        browser.end();
    }
};
