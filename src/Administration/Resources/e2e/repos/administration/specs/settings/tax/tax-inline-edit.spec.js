const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

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
        const page = settingsPage(browser);

        browser
            .waitForElementVisible(`${page.elements.gridRow}:first-child ${page.elements.contextMenuButton}`)
            .moveToElement(`${page.elements.gridRow}:first-child`, 5, 5).doubleClick()
            .fillField('input[name=sw-field--item-name]', 'Is this still a tax or already robbery', true)
            .fillField('input[name=sw-field--item-taxRate]', '80', true)
            .waitForElementVisible(`${page.elements.gridRow}__inline-edit-action`)
            .click(`${page.elements.gridRow}__inline-edit-action`)
            .waitForElementNotPresent('.is--inline-editing ');
    },
    'verify edited tax': (browser) => {
        const page = settingsPage(browser);

        browser
            .waitForElementVisible('.sw-settings-tax-list-grid')
            .waitForElementVisible(`${page.elements.gridRow}:first-child ${page.elements.taxColumnName}`)
            .assert.containsText(`${page.elements.gridRow}:first-child ${page.elements.taxColumnName}`, 'Is this still a tax or already robbery');
    },
    after: (browser) => {
        browser.end();
    }
};
