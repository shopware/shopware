const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

const fixture = {
    shortName: 'AD',
    name: 'Aserbaidschanische Drachme',
    factor: '0.12',
    symbol: 'A',
    decimalPrecision: '2'
};

module.exports = {
    '@tags': ['settings', 'currency-inline-edit', 'currency', 'inline-edit'],
    before: (browser, done) => {
        global.AdminFixtureService.create('currency', fixture).then(() => {
            done();
        });
    },
    'open currency module': (browser) => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-settings-currency')
            .assert.urlContains('#/sw/settings/currency/index');
    },
    'inline edit currency': (browser) => {
        const page = settingsPage(browser);

        browser
            .waitForElementVisible(`${page.elements.gridRow}--0`)
            .moveToElement(`${page.elements.gridRow}--0`, 5, 5).doubleClick()
            .fillField(`${page.elements.gridRow}--0 input[name=sw-field--item-name]`, 'Galactic Credits', true)
            .fillField(`${page.elements.gridRow}--0 input[name=sw-field--item-shortName]`, 'GCr', true)
            .fillField(`${page.elements.gridRow}--0 input[name=sw-field--item-symbol]`, '%', true)
            .fillField(`${page.elements.gridRow}--0 input[name=sw-field--item-factor]`, '2.58', true)
            .waitForElementVisible(`${page.elements.gridRow}--0 ${page.elements.gridRowInlineEdit}`)
            .click(`${page.elements.gridRow}--0 ${page.elements.gridRowInlineEdit}`)
            .waitForElementNotPresent('.is--inline-editing');
    },
    'verify edited currency': (browser) => {
        const page = settingsPage(browser);

        browser.expect.element(`${page.elements.gridRow}--0 ${page.elements.currencyColumnName}`).to.have.text.that.contains('Galactic Credits');
    }
};
