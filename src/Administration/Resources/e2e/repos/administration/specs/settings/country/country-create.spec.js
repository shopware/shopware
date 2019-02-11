const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings', 'country-create', 'country', 'create'],
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
    'create new country': (browser) => {
        const page = settingsPage(browser);

        browser
            .click('a[href="#/sw/settings/country/create"]')
            .waitForElementVisible('.sw-settings-country-detail .sw-card__content')
            .assert.urlContains('#/sw/settings/country/create')
            .assert.containsText(page.elements.cardTitle, 'Settings')
            .fillField('input[name=sw-field--country-name]', '1.Niemandsland')
            .waitForElementPresent('input[name=sw-field--country-active]')
            .tickCheckbox('input[name=sw-field--country-active]', 'on')
            .click(page.elements.countrySaveAction)
            .checkNotification('Country "1.Niemandsland" has been saved successfully.')
            .assert.urlContains('#/sw/settings/country/detail');
    },
    'go back to listing and verify creation': (browser) => {
        const page = settingsPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .waitForElementVisible('.sw-settings-country-list-grid')
            .waitForElementVisible(`${page.elements.gridRow}--0 ${page.elements.countryColumnName}`)
            .assert.containsText(`${page.elements.gridRow}--0 ${page.elements.countryColumnName}`, '1.Niemandsland');
    },
    after: (browser) => {
        browser.end();
    }
};
