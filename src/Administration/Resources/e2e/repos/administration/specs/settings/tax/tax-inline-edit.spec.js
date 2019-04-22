const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings', 'tax-inline-edit', 'tax', 'inline-edit'],
    before: (browser, done) => {
        global.AdminFixtureService.create('tax').then(() => {
            done();
        });
    },
    'open tax module': (browser) => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-settings-tax')
            .assert.urlContains('#/sw/settings/tax/index');
    },
    'inline edit tax': (browser) => {
        const page = settingsPage(browser);

        browser
            .waitForElementVisible(`${page.elements.gridRow}--0`)
            .moveToElement(`${page.elements.gridRow}--0`, 5, 5).doubleClick()
            .fillField(`${page.elements.gridRow}--0 input[name=sw-field--item-name]`, 'Is this still a tax or already robbery', true)
            .fillField(`${page.elements.gridRow}--0 input[name=sw-field--item-taxRate]`, '80', true)
            .click(`${page.elements.gridRow}--0 ${page.elements.gridRowInlineEdit}`)
            .waitForElementNotPresent('.is--inline-editing');
    },
    'verify edited tax': (browser) => {
        const page = settingsPage(browser);

        browser.expect.element(`${page.elements.gridRow}--0 ${page.elements.taxColumnName}`).to.have.text.that.contains('Is this still a tax or already robbery');
    }
};
