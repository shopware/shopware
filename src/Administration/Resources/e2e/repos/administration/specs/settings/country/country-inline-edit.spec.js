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
                mainMenuPath: '#/sw/settings/index',
                menuTitle: 'Settings',
                index: 6,
                subMenuItemPath: '#/sw/settings/country/index',
                subMenuTitle: 'Countries'
            });
    },
    'inline edit country': (browser) => {
        const page = settingsPage(browser);

        browser
            .waitForElementVisible(`${page.elements.gridRow}--0 ${page.elements.contextMenuButton}`)
            .moveToElement(`${page.elements.gridRow}--0`, 5, 5).doubleClick()
            .fillField(`${page.elements.gridRow}--0 input[name=sw-field--item-name]`, '1. Valhalla', true)
            .waitForElementVisible(`${page.elements.gridRow}--0 ${page.elements.gridRowInlineEdit}`)
            .click(`${page.elements.gridRow}--0 ${page.elements.gridRowInlineEdit}`)
            .waitForElementNotPresent('.is--inline-editing')
            .refresh();
    },
    'verify edited country': (browser) => {
        const page = settingsPage(browser);

        browser
            .waitForElementVisible('.sw-settings-country-list-grid')
            .waitForElementNotPresent(`${page.elements.alert}__message`)
            .waitForElementVisible(`${page.elements.gridRow}--0 ${page.elements.countryColumnName}`)
            .assert.containsText(`${page.elements.gridRow}--0 ${page.elements.countryColumnName}`, '1. Valhalla');
    },
    after: (browser) => {
        browser.end();
    }
};
