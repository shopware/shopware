module.exports = {
    '@tags': ['setting', 'language-delete', 'language', 'delete'],
    before: (browser, done) => {
        global.LanguageFixtureService.setLanguageFixtures().then(() => {
            done();
        });
    },
    'open language module and look for language to be deleted': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/language/index', 'Languages')
            .waitForElementVisible('.sw-grid-row:last-child .sw-language-list__column-name')
            .assert.containsText('.sw-grid-row:last-child .sw-language-list__column-name', 'Philippine English');
    },
    'delete language': (browser) => {
        browser
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
