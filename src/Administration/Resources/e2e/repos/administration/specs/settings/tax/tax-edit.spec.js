module.exports = {
    '@tags': ['setting','tax-edit', 'tax', 'edit'],
    before: (browser, done) => {
        global.FixtureService.create('tax').then(() => {
            done();
        });
    },
    'open tax module and look for the tax to be edited': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/tax/index', 'Tax')
            .waitForElementVisible('.sw-settings-tax-list-grid');
    },
    'edit tax': (browser) => {
        browser
            .waitForElementPresent('.sw-grid-row:last-child .sw-tax-list__column-name')
            .getLocationInView('.sw-grid-row:last-child .sw-tax-list__column-name')
            .waitForElementVisible('.sw-grid-row:last-child .sw-tax-list__column-name')
            .assert.containsText('.sw-grid-row:last-child .sw-tax-list__column-name', global.FixtureService.basicFixture.name)
            .clickContextMenuItem('.sw-tax-list__edit-action', '.sw-context-button__button','.sw-grid-row:last-child')
            .waitForElementVisible('.sw-settings-tax-detail .sw-card__content')
            .fillField('input[name=sw-field--tax-name]', 'Even higher tax rate','input', true)
            .waitForElementPresent('.sw-settings-tax-detail__save-action')
            .click('.sw-settings-tax-detail__save-action')
            .checkNotification('Tax "Even higher tax rate" has been saved successfully.')
            .assert.urlContains('#/sw/settings/tax/detail');
    },
    'verify edited tax': (browser) => {
        browser
            .click('a.smart-bar__back-btn')
            .waitForElementVisible('.sw-settings-tax-list-grid')
            .waitForElementVisible('.sw-grid-row:last-child .sw-tax-list__column-name')
            .assert.containsText('.sw-grid-row:last-child .sw-tax-list__column-name', 'Even higher tax rate');
    },
    after: (browser) => {
        browser.end();
    }
};
