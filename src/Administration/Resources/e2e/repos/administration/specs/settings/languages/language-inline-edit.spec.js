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
            .waitForElementVisible(`${page.elements.dataGridRow}--0`)
            .moveToElement(`${page.elements.dataGridRow}--0 ${page.elements.languageColumnName}`, 5, 5).doubleClick()
            .waitForElementVisible('.is--inline-edit')
            .fillField(`${page.elements.dataGridRow}--0 input[name=sw-field--item-name]`, 'Nordfriesisch', true)
            .waitForElementVisible(`${page.elements.dataGridRow}--0 ${page.elements.dataGridInlineEditSave}`)
            .click(`${page.elements.dataGridRow}--0 ${page.elements.dataGridInlineEditSave}`)
            .waitForElementPresent(`${page.elements.dataGridRow}--0 ${page.elements.languageColumnName}`)
            .expect.element(`${page.elements.dataGridRow}--0 ${page.elements.languageColumnName}`).to.have.text.that.contains('Nordfriesisch');
    }
};
