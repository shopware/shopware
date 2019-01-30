const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['setting', 'currency-delete', 'currency', 'delete'],
    before: (browser, done) => {
        global.FixtureService.create('currency').then(() => {
            done();
        });
    },
    'open currency module and look for currency to be deleted': (browser) => {
        const page = settingsPage(browser);

        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/currency/index', 'Currencies')
            .waitForElementVisible(`${page.elements.gridRow}--3 ${page.elements.currencyColumnName}`)
            .assert.containsText(`${page.elements.gridRow}--3 ${page.elements.currencyColumnName}`, global.FixtureService.basicFixture.name);
    },
    'delete currency': (browser) => {
        const page = settingsPage(browser);

        browser
            .clickContextMenuItem('.sw-context-menu-item--danger', page.elements.contextMenuButton, `${page.elements.gridRow}--3`)
            .waitForElementVisible(page.elements.modal)
            .assert.containsText(`${page.elements.modal} .sw-modal__body`, `Are you sure you want to delete the currency "${global.FixtureService.basicFixture.name}"?`)
            .click(`${page.elements.modal}__footer button${page.elements.primaryButton}`)
            .checkNotification(`Currency "${global.FixtureService.basicFixture.name}" has been deleted successfully.`);
    },
    after: (browser) => {
        browser.end();
    }
};
