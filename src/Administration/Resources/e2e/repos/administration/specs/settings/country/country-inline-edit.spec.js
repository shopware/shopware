module.exports = {
    '@tags': ['setting', 'country-inline-edit', 'country', 'inline-edit'],
    before: (browser, done) => {
        global.FixtureService.create('country').then(() => {
            done();
        });
    },
    'open country module': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/country/index', 'Countries');
    },
    'inline edit country': (browser) => {
        browser
            .waitForElementVisible('.sw-grid-row:first-child .sw-context-button__button')
            .moveToElement('.sw-grid-row:first-child', 5, 5).doubleClick()
            .fillField('input[name=sw-field--item-name]', '1. Valhalla', true)
            .waitForElementVisible('.sw-grid-row__inline-edit-action')
            .click('.sw-grid-row__inline-edit-action')
            .waitForElementNotPresent('.is--inline-editing')
            .refresh();
    },
    'verify edited country': (browser) => {
        browser
            .waitForElementVisible('.sw-settings-country-list-grid')
            .waitForElementNotPresent('.sw-alert__message')
            .waitForElementVisible('.sw-country-list__column-name:first-child')
            .assert.containsText('.sw-country-list__column-name:first-child', '1. Valhalla');
    },
    after: (browser) => {
        browser.end();
    }
};
