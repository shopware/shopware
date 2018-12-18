const countryFixture = global.FixtureService.loadJson('country.json');

module.exports = {
    '@tags': ['setting', 'country-delete', 'country', 'delete'],
    before: (browser, done) => {
        global.FixtureService.create('/v1/country', countryFixture, 'country', done);
    },
    'open country module and look for country to be deleted': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/country/index', 'Countries')
            .waitForElementVisible('.sw-country-list__column-name:first-child')
            .assert.containsText('.sw-country-list__column-name:first-child', countryFixture.name);
    },
    'delete country': (browser) => {
        browser
            .clickContextMenuItem('.sw-context-menu-item--danger', '.sw-context-button__button', '.sw-grid-row:first-child')
            .waitForElementVisible('.sw-modal')
            .assert.containsText('.sw-modal .sw-modal__body', `Are you sure you want to delete the country "${countryFixture.name}"?`)
            .click('.sw-modal__footer button.sw-button--primary')
            .checkNotification(`Country "${countryFixture.name}" has been deleted successfully.`);
    },
    after: (browser) => {
        browser.end();
    }
};
