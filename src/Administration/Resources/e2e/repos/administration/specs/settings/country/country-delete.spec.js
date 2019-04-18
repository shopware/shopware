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
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-settings-country')
            .assert.urlContains('#/sw/settings/country/index')
            .expect.element(`${page.elements.gridRow}--0`).to.have.text.that.contains(global.AdminFixtureService.basicFixture.name);
    },
    'delete country': (browser) => {
        const page = settingsPage(browser);

        browser
            .waitForElementNotPresent(page.elements.loader)
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-context-menu-item--danger',
                scope: `${page.elements.gridRow}--0`
            })
            .expect.element(`${page.elements.modal} .sw-modal__body`).to.have.text.that.contains(`Are you sure you want to delete the country "${global.AdminFixtureService.basicFixture.name}"?`);

        browser
            .click(`${page.elements.modal}__footer button${page.elements.primaryButton}`)
            .checkNotification(`Country "${global.AdminFixtureService.basicFixture.name}" has been deleted successfully.`);
    }
};
