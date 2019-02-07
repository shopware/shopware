const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings', 'language-delete', 'language', 'delete'],
    before: (browser, done) => {
        global.LanguageFixtureService.setLanguageFixtures().then(() => {
            done();
        });
    },
    'open language module and look for language to be deleted': (browser) => {
        const page = settingsPage(browser);

        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/language/index', 'Languages')
            .waitForElementVisible(`${page.elements.gridRow}--2 ${page.elements.languageColumnName}`)
            .assert.containsText(`${page.elements.gridRow}--2 ${page.elements.languageColumnName}`, global.LanguageFixtureService.languageFixture.name);
    },
    'delete language': (browser) => {
        const page = settingsPage(browser);

        browser
            .clickContextMenuItem('.sw-context-menu-item--danger', page.elements.contextMenuButton, `${page.elements.gridRow}--2`)
            .waitForElementVisible(page.elements.modal)
            .assert.containsText(
                '.sw-modal .sw-modal__body',
                'Are you sure you want to delete the language "Philippine English"?'
            )
            .click(`${page.elements.modal}__footer button${page.elements.primaryButton}`)
            .checkNotification('Language "Philippine English" has been deleted successfully.');
    },
    after: (browser) => {
        browser.end();
    }
};
