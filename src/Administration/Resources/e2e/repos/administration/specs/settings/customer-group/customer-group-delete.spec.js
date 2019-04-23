const settingsPage = require('administration/page-objects/module/sw-settings.page-object.js');

module.exports = {
    '@tags': ['settings', 'customer-group-delete', 'customer-group', 'delete'],
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
    'find customer group to be deleted': (browser) => {
        const page = settingsPage(browser);

        browser
            .expect.element(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).to.have.text.that.contains('Chuck-Testers');
    },
    'delete customer group': (browser) => {
        const page = settingsPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-context-menu-item--danger',
                scope: `${page.elements.dataGridRow}--0`
            })
            .expect.element(`${page.elements.modal} .sw-modal__body`).to.have.text.that.contains(`Are you sure you want to delete the customer group "${global.AdminFixtureService.basicFixture.name}"?`);


        browser
            .click(`${page.elements.modal}__footer button${page.elements.primaryButton}`)
            .checkNotification('Customer group has been deleted successfully.');
    }
};
