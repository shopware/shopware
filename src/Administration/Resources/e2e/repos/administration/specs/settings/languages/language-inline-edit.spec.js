module.exports = {
    '@tags': ['setting','language-inline-edit', 'language', 'inline-edit'],
    before: (browser, done) => {
        global.LanguageFixtureService.setLanguageFixtures().then(() => {
            done();
        });
    },
    'open language module': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/language/index', 'Languages');
    },
    'inline edit language': (browser) => {
        browser
            .waitForElementVisible('.sw-grid-row:first-child .sw-context-button__button')
            .moveToElement('.sw-grid-row:first-child', 5, 5).doubleClick()
            .fillField('.sw-grid-row:first-child input[name=sw-field--item-name]', 'Nordfriesisch', true)
            .waitForElementVisible('.sw-grid-row__inline-edit-action')
            .click('.sw-grid-row__inline-edit-action')
            .waitForElementNotPresent('.is--inline-editing ');
    },
    'verify edited language': (browser) => {
        browser
            .waitForElementVisible('.sw-settings-language-list-grid')
            .waitForElementVisible('.sw-grid-row:first-child .sw-language-list__column-name')
            .assert.containsText('.sw-grid-row:first-child .sw-language-list__column-name', 'Nordfriesisch');
    },
    after: (browser) => {
        browser.end();
    }
};
