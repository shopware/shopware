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
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-settings-country')
            .assert.urlContains('#/sw/settings/country/index')
            .waitForElementNotPresent(`${page.elements.alert}__message`)
            .expect.element(`${page.elements.gridRow}--0 ${page.elements.countryColumnName}`).to.have.text.that.contains(global.AdminFixtureService.basicFixture.name);
    },
    'edit country': (browser) => {
        const page = settingsPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-country-list__edit-action',
                scope: `${page.elements.gridRow}--0`
            })
            .fillField('input[name=sw-field--country-name]', '1.Niemandsland x2', true)
            .click(page.elements.countrySaveAction)
            .checkNotification('Country "1.Niemandsland x2" has been saved successfully.')
            .assert.urlContains('#/sw/settings/country/detail');
    },
    'verify edited country': (browser) => {
        const page = settingsPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .expect.element(`${page.elements.gridRow}--0 ${page.elements.countryColumnName}`).to.have.text.that.contains('1.Niemandsland x2');
    }
};
