const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings', 'country-edit', 'country', 'edit'],
    before: (browser, done) => {
        global.AdminFixtureService.create('country').then(() => {
            done();
        });
    },
    'open country module and look for country to be edited': (browser) => {
        const page = settingsPage(browser);

        browser
            .openMainMenuEntry({
                mainMenuPath: '#/sw/settings/index',
                menuTitle: 'Settings',
                index: 5,
                subMenuItemPath: '#/sw/settings/country/index',
                subMenuTitle: 'Countries'
            })
            .waitForElementVisible('.sw-settings-country-list-grid')
            .waitForElementNotPresent(`${page.elements.alert}__message`)
            .waitForElementVisible(`${page.elements.gridRow}--0 ${page.elements.countryColumnName}`)
            .assert.containsText(`${page.elements.gridRow}--0 ${page.elements.countryColumnName}`, global.AdminFixtureService.basicFixture.name);
    },
    'edit country': (browser) => {
        const page = settingsPage(browser);

        browser
            .clickContextMenuItem('.sw-country-list__edit-action', page.elements.contextMenuButton, `${page.elements.gridRow}--0`)
            .waitForElementVisible('.sw-settings-country-detail .sw-card__content')
            .assert.containsText(page.elements.cardTitle, 'Settings')
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
            .waitForElementNotPresent(`${page.elements.alert}__message`)
            .waitForElementVisible(`${page.elements.gridRow}--0 ${page.elements.countryColumnName}`)
            .assert.containsText(`${page.elements.gridRow}--0 ${page.elements.countryColumnName}`, '1.Niemandsland x2');
    },
    after: (browser) => {
        browser.end();
    }
};
