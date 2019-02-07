const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings', 'tax-delete', 'tax', 'delete'],
    before: (browser, done) => {
        global.FixtureService.create('tax').then(() => {
            done();
        });
    },
    'open tax module and look for tax to be deleted': (browser) => {
        const page = settingsPage(browser);

        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/tax/index', 'Tax')
            .waitForElementVisible('.sw-settings-tax-list-grid')
            .waitForElementVisible(`${page.elements.gridRow}--5 ${page.elements.taxColumnName}`)
            .assert.containsText(`${page.elements.gridRow}--5 ${page.elements.taxColumnName}`, global.FixtureService.basicFixture.name);
    },
    'delete tax': (browser) => {
        const page = settingsPage(browser);

        browser
            .clickContextMenuItem('.sw-context-menu-item--danger', page.elements.contextMenuButton, `${page.elements.gridRow}--5`)
            .waitForElementVisible(page.elements.modal)
            .assert.containsText(`${page.elements.modal} ${page.elements.modal}__body`, `Are you sure you want to delete the tax "${global.FixtureService.basicFixture.name}"?`)
            .click(`${page.elements.modal}__footer button${page.elements.primaryButton}`)
            .checkNotification(`Tax "${global.FixtureService.basicFixture.name}" has been deleted successfully.`);
    },
    after: (browser) => {
        browser.end();
    }
};
