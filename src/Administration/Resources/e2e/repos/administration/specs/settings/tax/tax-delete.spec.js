const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings', 'tax-delete', 'tax', 'delete'],
    before: (browser, done) => {
        global.AdminFixtureService.create('tax').then(() => {
            done();
        });
    },
    'open tax module and look for tax to be deleted': (browser) => {
        const page = settingsPage(browser);

        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-settings-tax')
            .assert.urlContains('#/sw/settings/tax/index')
            .expect.element(`${page.elements.gridRow}--5 ${page.elements.taxColumnName}`).to.have.text.that.contains(global.AdminFixtureService.basicFixture.name);
    },
    'delete tax': (browser) => {
        const page = settingsPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-context-menu-item--danger',
                scope: `${page.elements.gridRow}--5`
            })
            .expect.element(`${page.elements.modal}__body`).to.have.text.that.equals(`Are you sure you want to delete the tax "${global.AdminFixtureService.basicFixture.name}"?`);

        browser
            .click(`${page.elements.modal}__footer button${page.elements.primaryButton}`)
            .checkNotification(`Tax "${global.AdminFixtureService.basicFixture.name}" has been deleted successfully.`);
    }
};
