module.exports = {
    '@tags': ['setting', 'country-delete', 'country', 'delete'],
    before: (browser, done) => {
        global.FixtureService.create('country').then(() => {
            done();
        });
    },
    'open country module and look for country to be deleted': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/country/index', 'Countries')
            .waitForElementVisible('.sw-country-list__column-name:first-child')
            .assert.containsText('.sw-country-list__column-name:first-child', global.FixtureService.basicFixture.name);
    },
    'delete country': (browser) => {
        browser
            .clickContextMenuItem('.sw-context-menu-item--danger', '.sw-context-button__button', '.sw-grid-row:first-child')
            .waitForElementVisible('.sw-modal')
            .assert.containsText('.sw-modal .sw-modal__body', `Are you sure you want to delete the country "${global.FixtureService.basicFixture.name}"?`)
            .click('.sw-modal__footer button.sw-button--primary')
            .checkNotification(`Country "${global.FixtureService.basicFixture.name}" has been deleted successfully.`);
    },
    after: (browser) => {
        browser.end();
    }
};
