const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings', 'country-inline-edit', 'country', 'inline-edit'],
    before: (browser, done) => {
        global.AdminFixtureService.create('country').then(() => {
            done();
        });
    },
    'open country module': (browser) => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-settings-country')
            .assert.urlContains('#/sw/settings/country/index');
    },
    'inline edit country': (browser) => {
        const page = settingsPage(browser);

        browser
            .waitForElementVisible(`${page.elements.gridRow}--0`)
            .moveToElement(`${page.elements.gridRow}--0`, 5, 5).doubleClick()
            .fillField(`${page.elements.gridRow}--0 input[name=sw-field--item-name]`, '1. Valhalla', true)
            .click(`${page.elements.gridRow}--0 ${page.elements.gridRowInlineEdit}`)
            .waitForElementNotPresent('.is--inline-editing');
    },
    'verify edited country': (browser) => {
        const page = settingsPage(browser);

        browser
            .refresh()
            .expect.element(`${page.elements.gridRow}--0 ${page.elements.countryColumnName}`).to.have.text.that.contains('1. Valhalla');
    }
};
