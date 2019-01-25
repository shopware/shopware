const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['setting', 'country-edit', 'country', 'edit'],
    before: (browser, done) => {
        global.FixtureService.create('country').then(() => {
            done();
        });
    },
    'open country module and look for country to be edited': (browser) => {
        const page = settingsPage(browser);

        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/country/index', 'Countries')
            .waitForElementVisible('.sw-settings-country-list-grid')
            .waitForElementNotPresent(`${page.elements.alert}__message`)
            .waitForElementVisible(`${page.elements.countryColumnName}:first-child`)
            .assert.containsText(`${page.elements.countryColumnName}:first-child`,  global.FixtureService.basicFixture.name);
    },
    'edit country': (browser) => {
        const page = settingsPage(browser);

        browser
            .clickContextMenuItem('.sw-country-list__edit-action', page.elements.contextMenuButton, `${page.elements.gridRow}:first-child`)
            .waitForElementVisible('.sw-settings-country-detail .sw-card__content')
            .assert.containsText('.sw-card__title', 'Settings')
            .fillField('input[name=sw-field--country-name]', '1.Niemandsland x2', true)
            .click(page.elements.countrySaveAction)
            .checkNotification('Country "1.Niemandsland x2" has been saved successfully.')
            .assert.urlContains('#/sw/settings/country/detail');
    },
    'verify edited country': (browser) => {
        const page = settingsPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .waitForElementVisible('.sw-settings-country-list-grid')
            .waitForElementNotPresent('.sw-alert__message')
            .waitForElementVisible(`${page.elements.countryColumnName}:first-child`)
            .assert.containsText(`${page.elements.countryColumnName}:first-child`, '1.Niemandsland x2');
    },
    after: (browser) => {
        browser.end();
    }
};
