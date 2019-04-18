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
            .openMainMenuEntry({
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-settings-language')
            .assert.urlContains('#/sw/settings/language/index')
            .expect.element(`${page.elements.gridRow}--2 ${page.elements.languageColumnName}`).to.have.text.that.contains(global.LanguageFixtureService.getLanguageName());
    },
    'delete language': (browser) => {
        const page = settingsPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-context-menu-item--danger',
                scope: `${page.elements.gridRow}--2`
            })
            .expect.element('.sw-modal .sw-modal__body').to.have.text.that.contains('Are you sure you want to delete the language "Philippine English"?');

        browser
            .click(`${page.elements.modal}__footer button${page.elements.primaryButton}`)
            .checkNotification('Language "Philippine English" has been deleted successfully.');
    },
    'check if default language cannot be deleted': (browser) => {
        const page = settingsPage(browser);

        browser
            .waitForElementVisible(`${page.elements.gridRow}--1 .icon--small-default-checkmark-line-medium`)
            .click(`${page.elements.gridRow}--1 ${page.elements.contextMenuButton}`)
            .waitForElementNotPresent('.sw-context-menu-item--danger')
            .expect.element('.sw-context-menu-item').to.have.text.that.not.contains('Delete');

        browser
            .click('.sw-context-menu-item')
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains('English');
    }
};
