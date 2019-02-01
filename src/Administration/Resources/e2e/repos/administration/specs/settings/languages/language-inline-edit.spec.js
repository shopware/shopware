const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

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
        const page = settingsPage(browser);

        browser
            .waitForElementVisible(`${page.elements.gridRow}--0 ${page.elements.contextMenuButton}`)
            .moveToElement(`${page.elements.gridRow}--0`, 5, 5).doubleClick()
            .fillField(`${page.elements.gridRow}--0 input[name=sw-field--item-name]`, 'Nordfriesisch', true)
            .waitForElementVisible(`${page.elements.gridRow}--0 ${page.elements.gridRowInlineEdit}`)
            .click(`${page.elements.gridRow}--0 ${page.elements.gridRowInlineEdit}`)
            .waitForElementNotPresent('.is--inline-editing');
    },
    'verify edited language': (browser) => {
        const page = settingsPage(browser);

        browser
            .waitForElementVisible('.sw-settings-language-list-grid')
            .waitForElementVisible(`${page.elements.gridRow}--0 ${page.elements.languageColumnName}`)
            .assert.containsText(`${page.elements.gridRow}--0 ${page.elements.languageColumnName}`, 'Nordfriesisch');
    },
    after: (browser) => {
        browser.end();
    }
};
