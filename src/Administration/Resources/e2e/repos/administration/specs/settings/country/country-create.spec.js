const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings', 'country-create', 'country', 'create'],
    'open country module': (browser) => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-settings-country')
            .assert.urlContains('#/sw/settings/country/index');
    },
    'create new country': (browser) => {
        const page = settingsPage(browser);

        browser
            .click('a[href="#/sw/settings/country/create"]')
            .expect.element(page.elements.cardTitle).to.have.text.that.contains('Settings');

        browser
            .assert.urlContains('#/sw/settings/country/create')
            .fillField('input[name=sw-field--country-name]', '1.Niemandsland')
            .waitForElementPresent('input[name=sw-field--country-active]')
            .tickCheckbox('input[name=sw-field--country-active]', true)
            .click(page.elements.countrySaveAction)
            .checkNotification('Country "1.Niemandsland" has been saved successfully.')
            .assert.urlContains('#/sw/settings/country/detail');
    },
    'go back to listing and verify creation': (browser) => {
        const page = settingsPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .expect.element(`${page.elements.gridRow}--0 ${page.elements.countryColumnName}`).to.have.text.that.contains('1.Niemandsland');
    }
};
