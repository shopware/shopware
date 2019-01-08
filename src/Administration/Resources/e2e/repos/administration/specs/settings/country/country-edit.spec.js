module.exports = {
    '@tags': ['setting', 'country-edit', 'country', 'edit'],
    before: (browser, done) => {
        global.FixtureService.create('country').then(() => {
            done();
        });
    },
    'open country module and look for country to be edited': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/country/index', 'Countries')
            .waitForElementVisible('.sw-settings-country-list-grid')
            .waitForElementNotPresent('.sw-alert__message')
            .waitForElementVisible('.sw-country-list__column-name:first-child')
            .assert.containsText('.sw-country-list__column-name:first-child',  global.FixtureService.basicFixture.name);
    },
    'edit country': (browser) => {
        browser
            .clickContextMenuItem('.sw-country-list__edit-action', '.sw-context-button__button', '.sw-grid-row:first-child')
            .waitForElementVisible('.sw-settings-country-detail .sw-card__content')
            .assert.containsText('.sw-card__title', 'Settings')
            .fillField('input[name=sw-field--country-name]', '1.Niemandsland x2')
            .click('.sw-settings-country-detail__save-action')
            .checkNotification('Country "1.Niemandsland x2" has been saved successfully.')
            .assert.urlContains('#/sw/settings/country/detail');
    },
    'verify edited country': (browser) => {
        browser
            .click('a.smart-bar__back-btn')
            .waitForElementVisible('.sw-settings-country-list-grid')
            .waitForElementNotPresent('.sw-alert__message')
            .waitForElementVisible('.sw-country-list__column-name:first-child')
            .assert.containsText('.sw-country-list__column-name:first-child', '1.Niemandsland x2');
    },
    after: (browser) => {
        browser.end();
    }
};
