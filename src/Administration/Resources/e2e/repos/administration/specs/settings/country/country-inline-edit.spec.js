const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['setting', 'country-inline-edit', 'country', 'inline-edit'],
    before: (browser, done) => {
        global.FixtureService.create('country').then(() => {
            done();
        });
    },
    'open country module': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/country/index', 'Countries');
    },
    'inline edit country': (browser) => {
        const page = settingsPage(browser);

        browser
            .waitForElementVisible(`${page.elements.gridRow}:first-child ${page.elements.contextMenuButton}`)
            .moveToElement(`${page.elements.gridRow}:first-child`, 5, 5).doubleClick()
            .fillField('input[name=sw-field--item-name]', '1. Valhalla', true)
            .waitForElementVisible(`${page.elements.gridRow}__inline-edit-action`)
            .click(`${page.elements.gridRow}__inline-edit-action`)
            .waitForElementNotPresent('.is--inline-editing')
            .refresh();
    },
    'verify edited country': (browser) => {
        const page = settingsPage(browser);

        browser
            .waitForElementVisible('.sw-settings-country-list-grid')
            .waitForElementNotPresent(`${page.elements.alert}__message`)
            .waitForElementVisible(`${page.elements.countryColumnName}:first-child`)
            .assert.containsText(`${page.elements.countryColumnName}:first-child`, '1. Valhalla');
    },
    after: (browser) => {
        browser.end();
    }
};
