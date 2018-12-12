const fixture = {
    shortName: 'AD',
    name: 'Aserbaidschanische Drachme',
    factor: '0.12',
    symbol: 'A'
};

module.exports = {
    '@tags': ['setting','currency-inline-edit', 'currency', 'inline-edit'],
    before: (browser, done) => {
        global.FixtureService.create('currency', fixture).then(() => {
            done();
        });
    },
    'open currency module': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/currency/index', 'Currencies');
    },
    'inline edit currency': (browser) => {
        browser
            .waitForElementVisible('.sw-grid-row:first-child .sw-context-button__button')
            .moveToElement('.sw-grid-row:first-child', 5, 5).doubleClick()
            .fillField('input[name=sw-field--item-name]', 'Galactic Credits', true)
            .fillField('input[name=sw-field--item-shortName]', 'GCr', true)
            .fillField('input[name=sw-field--item-symbol]', '%', true)
            .fillField('input[name=sw-field--item-factor]', '2.58', true)
            .waitForElementVisible('.sw-grid-row__inline-edit-action')
            .click('.sw-grid-row__inline-edit-action')
            .waitForElementNotPresent('.is--inline-editing ');
    },
    'verify edited currency': (browser) => {
        browser
            .waitForElementVisible('.sw-settings-currency-list-grid')
            .waitForElementVisible('.sw-grid-row:first-child .sw-currency-list__column-name')
            .assert.containsText('.sw-grid-row:first-child .sw-currency-list__column-name', 'Galactic Credits');
    },
    after: (browser) => {
        browser.end();
    }
};
