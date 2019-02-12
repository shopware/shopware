const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings', 'tax-edit', 'tax', 'edit'],
    before: (browser, done) => {
        global.AdminFixtureService.create('tax').then(() => {
            done();
        });
    },
    'open tax module and look for the tax to be edited': (browser) => {
        browser
            .openMainMenuEntry({
                mainMenuPath: '#/sw/settings/index',
                menuTitle: 'Settings',
                index: 5,
                subMenuItemPath: '#/sw/settings/tax/index',
                subMenuTitle: 'Tax'
            })
            .waitForElementVisible('.sw-settings-tax-list-grid');
    },
    'edit tax': (browser) => {
        const page = settingsPage(browser);

        browser
            .waitForElementPresent(`${page.elements.gridRow}--5 ${page.elements.taxColumnName}`)
            .getLocationInView(`${page.elements.gridRow}--5 ${page.elements.taxColumnName}`)
            .waitForElementVisible(`${page.elements.gridRow}--5 ${page.elements.taxColumnName}`)
            .assert.containsText(`${page.elements.gridRow}--5 ${page.elements.taxColumnName}`, global.AdminFixtureService.basicFixture.name)
            .clickContextMenuItem('.sw-tax-list__edit-action', page.elements.contextMenuButton, `${page.elements.gridRow}--5`)
            .waitForElementVisible('.sw-settings-tax-detail .sw-card__content')
            .fillField('input[name=sw-field--tax-name]', 'Even higher tax rate', true)
            .waitForElementPresent(page.elements.taxSaveAction)
            .click(page.elements.taxSaveAction)
            .checkNotification('Tax "Even higher tax rate" has been saved successfully.')
            .assert.urlContains('#/sw/settings/tax/detail');
    },
    'verify edited tax': (browser) => {
        const page = settingsPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .waitForElementVisible('.sw-settings-tax-list-grid')
            .waitForElementVisible(`${page.elements.gridRow}--5 ${page.elements.taxColumnName}`)
            .assert.containsText(`${page.elements.gridRow}--5 ${page.elements.taxColumnName}`, 'Even higher tax rate');
    },
    after: (browser) => {
        browser.end();
    }
};
