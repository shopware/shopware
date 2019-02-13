const integrationPage = require('../../../page-objects/module/sw-integration.page-object.js');

module.exports = {
    '@tags': ['settings', 'integration-edit', 'integration', 'edit'],
    before: (browser, done) => {
        global.IntegrationFixtureService.setIntegrationFixtures().then(() => {
            done();
        });
    },
    'open integration module and look for the integration to be edited': (browser) => {
        const page = integrationPage(browser);

        browser
            .openMainMenuEntry({
                targetPath: '#/sw/integration/index',
                mainMenuId: 'sw-settings',
                subMenuId: 'sw-integration'
            })
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains('Integrations');

        browser.expect.element(`${page.elements.gridRow}--0 ${page.elements.listColumnName}`).to.have.text.that.contains(global.IntegrationFixtureService.integrationFixture.name);
    },
    'edit integration': (browser) => {
        const page = integrationPage(browser);

        browser
            .clickContextMenuItem('.sw_integration_list__edit-action',page.elements.contextMenuButton)
            .expect.element(page.elements.modalTitle).to.have.text.that.contains('Edit:');

        browser
            .fillField(page.elements.integrationName, 'Once again: Edits integration', true)
            .click(page.elements.integrationSaveAction)
            .checkNotification('Integration has been saved successfully')
            .assert.urlContains('#/sw/integration/index');
    },
    'verify saving of edited integration': (browser) => {
        const page = integrationPage(browser);

        browser
            .refresh()
            .clickContextMenuItem('.sw_integration_list__edit-action',page.elements.contextMenuButton)
            .waitForElementVisible(page.elements.modalTitle);
    },
    'close modal without saving': (browser) => {
        const page = integrationPage(browser);

        browser
            .click(`${page.elements.modal}__close`)
            .waitForElementNotPresent(page.elements.modal)
            .expect.element(`${page.elements.listColumnName} .sw-grid__cell-content`).to.have.text.that.contains('Once again: Edits integration');
    },
    after: (browser) => {
        browser.end();
    }
};
