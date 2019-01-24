module.exports = {
    '@tags': ['setting','tax-inline-edit', 'tax', 'inline-edit'],
    before: (browser, done) => {
        global.FixtureService.create('tax').then(() => {
            done();
        });
    },
    'open tax module': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/tax/index', 'Tax');
    },
    'inline edit tax': (browser) => {
        browser
            .waitForElementVisible('.sw-grid-row:first-child .sw-context-button__button')
            .moveToElement('.sw-grid-row:first-child', 5, 5).doubleClick()
            .fillField('input[name=sw-field--item-name]', 'Is this still a tax or already robbery', true)
            .fillField('input[name=sw-field--item-taxRate]', '80', true)
            .waitForElementVisible('.sw-grid-row__inline-edit-action')
            .click('.sw-grid-row__inline-edit-action')
            .waitForElementNotPresent('.is--inline-editing ');
    },
    'verify edited tax': (browser) => {
        browser
            .waitForElementVisible('.sw-settings-tax-list-grid')
            .waitForElementVisible('.sw-grid-row:first-child .sw-tax-list__column-name')
            .assert.containsText('.sw-grid-row:first-child .sw-tax-list__column-name', 'Is this still a tax or already robbery');
    },
    after: (browser) => {
        browser.end();
    }
};
