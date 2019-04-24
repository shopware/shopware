const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings', 'customer-group-edit', 'customer-group', 'edit'],
    before: (browser, done) => {
        global.AdminFixtureService.create('customer-group').then(() => {
            done();
        });
    },
    'open customer group module': (browser) => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-settings-customer-group')
            .assert.urlContains('#/sw/settings/customer/group/index');
    },
    'find customer group to be edited': (browser) => {
        const page = settingsPage(browser);

        browser
            .expect.element(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).to.have.text.that.contains('Chuck-Testers');
    },
    'edit customer group': (browser) => {
        const page = settingsPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-context-menu-item:nth-of-type(1)',
                scope: `${page.elements.dataGridRow}--0`
            })
            .fillField('input[name=sw-field--customerGroup-name]', 'E2E Merchant', true)
            .tickCheckbox('input[name=sw-field--customerGroup-displayGross]', false)
            .expect.element(page.elements.customerGroupSaveAction).to.be.enabled;

        browser.click(page.elements.customerGroupSaveAction)
            .checkNotification('Customer group "E2E Merchant" has been saved successfully.')
            .assert.urlContains('#/sw/settings/customer/group/detail');
    },
    'go back to listing and verify creation': (browser) => {
        const page = settingsPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .refresh()
            .expect.element(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).to.have.text.that.contains('E2E Merchant');
        browser.expect.element(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--displayGross`).to.have.text.that.contains('Net');
    }
};
