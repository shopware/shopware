const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings', 'currency-delete', 'currency', 'delete'],
    before: (browser, done) => {
        global.AdminFixtureService.create('currency').then(() => {
            done();
        });
    },
    'open currency module and look for currency to be deleted': (browser) => {
        const page = settingsPage(browser);

        browser
            .openMainMenuEntry({
                mainMenuPath: '#/sw/settings/index',
                menuTitle: 'Settings',
                index: 6,
                subMenuItemPath: '#/sw/settings/currency/index',
                subMenuTitle: 'Currencies'
            })
            .waitForElementVisible(`${page.elements.gridRow}--3 ${page.elements.currencyColumnName}`)
            .assert.containsText(`${page.elements.gridRow}--3 ${page.elements.currencyColumnName}`, global.AdminFixtureService.basicFixture.name);
    },
    'delete currency': (browser) => {
        const page = settingsPage(browser);

        browser
            .clickContextMenuItem('.sw-context-menu-item--danger', page.elements.contextMenuButton, `${page.elements.gridRow}--3`)
            .waitForElementVisible(page.elements.modal)
            .assert.containsText(`${page.elements.modal} .sw-modal__body`, `Are you sure you want to delete the currency "${global.AdminFixtureService.basicFixture.name}"?`)
            .click(`${page.elements.modal}__footer button${page.elements.primaryButton}`)
            .checkNotification(`Currency "${global.AdminFixtureService.basicFixture.name}" has been deleted successfully.`);
    },
    after: (browser) => {
        browser.end();
    }
};
