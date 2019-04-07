const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings', 'language-inline-edit', 'language', 'inline-edit'],
    before: (browser, done) => {
        global.LanguageFixtureService.setLanguageFixtures().then(() => {
            done();
        });
    },
    'open language module': (browser) => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-settings-language')
            .assert.urlContains('#/sw/settings/language/index');
    },
    'inline edit language': (browser) => {
        const page = settingsPage(browser);

        browser
            .waitForElementVisible(`${page.elements.gridRow}--0`)
            .moveToElement(`${page.elements.gridRow}--0`, 5, 5).doubleClick()
            .fillField(`${page.elements.gridRow}--0 input[name=sw-field--item-name]`, 'Nordfriesisch', true)
            .waitForElementVisible(`${page.elements.gridRow}--0 ${page.elements.gridRowInlineEdit}`)
            .click(`${page.elements.gridRow}--0 ${page.elements.gridRowInlineEdit}`)
            .waitForElementNotPresent('.is--inline-editing');
    },
    'verify edited language': (browser) => {
        const page = settingsPage(browser);

        browser.expect.element(`${page.elements.gridRow}--0 ${page.elements.languageColumnName}`).to.have.text.that.contains('Nordfriesisch');
    }
};
