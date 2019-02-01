const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['setting', 'country-delete', 'country', 'delete'],
    before: (browser, done) => {
        global.FixtureService.create('country').then(() => {
            done();
        });
    },
    'open country module and look for country to be deleted': (browser) => {
        const page = settingsPage(browser);

        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/country/index', 'Countries')
            .waitForElementVisible(`${page.elements.gridRow}--0 ${page.elements.countryColumnName}:first-child`)
            .assert.containsText(`${page.elements.gridRow}--0`, global.FixtureService.basicFixture.name);
    },
    'delete country': (browser) => {
        const page = settingsPage(browser);

        browser
            .clickContextMenuItem('.sw-context-menu-item--danger', page.elements.contextMenuButton, `${page.elements.gridRow}--0`)
            .waitForElementVisible(page.elements.modal)
            .assert.containsText(`${page.elements.modal} .sw-modal__body`, `Are you sure you want to delete the country "${global.FixtureService.basicFixture.name}"?`)
            .click(`${page.elements.modal}__footer button${page.elements.primaryButton}`)
            .checkNotification(`Country "${global.FixtureService.basicFixture.name}" has been deleted successfully.`);
    },
    after: (browser) => {
        browser.end();
    }
};
