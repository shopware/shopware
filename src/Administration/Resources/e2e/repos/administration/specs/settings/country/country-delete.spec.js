const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings', 'country-delete', 'country', 'delete'],
    before: (browser, done) => {
        global.AdminFixtureService.create('country').then(() => {
            done();
        });
    },
    'open country module and look for country to be deleted': (browser) => {
        const page = settingsPage(browser);

        browser
            .openMainMenuEntry({
                mainMenuPath: '#/sw/settings/index',
                menuTitle: 'Settings',
                index: 5,
                subMenuItemPath: '#/sw/settings/country/index',
                subMenuTitle: 'Countries'
            })
            .waitForElementVisible(`${page.elements.gridRow}--0 ${page.elements.countryColumnName}:first-child`)
            .assert.containsText(`${page.elements.gridRow}--0`, global.AdminFixtureService.basicFixture.name);
    },
    'delete country': (browser) => {
        const page = settingsPage(browser);

        browser
            .clickContextMenuItem('.sw-context-menu-item--danger', page.elements.contextMenuButton, `${page.elements.gridRow}--0`)
            .waitForElementVisible(page.elements.modal)
            .assert.containsText(`${page.elements.modal} .sw-modal__body`, `Are you sure you want to delete the country "${global.AdminFixtureService.basicFixture.name}"?`)
            .click(`${page.elements.modal}__footer button${page.elements.primaryButton}`)
            .checkNotification(`Country "${global.AdminFixtureService.basicFixture.name}" has been deleted successfully.`);
    },
    after: (browser) => {
        browser.end();
    }
};
