const taxFixture = global.FixtureService.loadJson('tax.json');

module.exports = {
    '@tags': ['setting', 'tax-delete', 'tax', 'delete'],
    before: (browser, done) => {
        global.FixtureService.create('/v1/tax', taxFixture, 'tax', done);
    },
    'open tax module and look for tax to be deleted': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/tax/index', 'Tax')
            .waitForElementVisible('.sw-settings-tax-list-grid')
            .waitForElementVisible('.sw-grid-row:last-child .sw-tax-list__column-name')
            .assert.containsText('.sw-grid-row:last-child .sw-tax-list__column-name', taxFixture.name);
    },
    'delete tax': (browser) => {
        browser
            .clickContextMenuItem('.sw-context-menu-item--danger', '.sw-context-button__button', '.sw-grid-row:last-child')
            .waitForElementVisible('.sw-modal')
            .assert.containsText('.sw-modal .sw-modal__body', `Are you sure you want to delete the tax "${taxFixture.name}"?`)
            .click('.sw-modal__footer button.sw-button--primary')
            .checkNotification(`Tax "${taxFixture.name}" has been deleted successfully.`);
    },
    after: (browser) => {
        browser.end();
    }
};
