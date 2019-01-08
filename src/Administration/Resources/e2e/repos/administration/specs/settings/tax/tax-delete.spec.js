module.exports = {
    '@tags': ['setting', 'tax-delete', 'tax', 'delete'],
    before: (browser, done) => {
        global.FixtureService.create('tax').then(() => {
            done();
        });
    },
    'open tax module and look for tax to be deleted': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/tax/index', 'Tax')
            .waitForElementVisible('.sw-settings-tax-list-grid')
            .waitForElementVisible('.sw-grid-row:last-child .sw-tax-list__column-name')
            .assert.containsText('.sw-grid-row:last-child .sw-tax-list__column-name', global.FixtureService.basicFixture.name);
    },
    'delete tax': (browser) => {
        browser
            .clickContextMenuItem('.sw-context-menu-item--danger', '.sw-context-button__button', '.sw-grid-row:last-child')
            .waitForElementVisible('.sw-modal')
            .assert.containsText('.sw-modal .sw-modal__body', `Are you sure you want to delete the tax "${global.FixtureService.basicFixture.name}"?`)
            .click('.sw-modal__footer button.sw-button--primary')
            .checkNotification(`Tax "${global.FixtureService.basicFixture.name}" has been deleted successfully.`);
    },
    after: (browser) => {
        browser.end();
    }
};
