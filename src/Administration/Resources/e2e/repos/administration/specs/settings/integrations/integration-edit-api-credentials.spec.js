const integrationPage = require('../../../page-objects/module/sw-integration.page-object.js');

module.exports = {
    '@tags': ['settings', 'integration-api-credentials', 'edit', 'integration', 'api-credentials'],
    before: (browser, done) => {
        global.IntegrationFixtureService.setIntegrationFixtures().then(() => {
            done();
        });
    },
    'open integration module and look for the integration to be edited': (browser) => {
        const page = integrationPage(browser);

        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-integration')
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains('Integrations');

        browser
            .assert.urlContains('#/sw/integration/index')
            .expect.element(`${page.elements.listColumnName} .sw-grid__cell-content`).to.have.text.that.contains(global.IntegrationFixtureService.integrationFixture.name);
    },
    'check the clipboard': (browser) => {
        const page = integrationPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw_integration_list__edit-action'
            })
            .expect.element(page.elements.modalTitle).to.have.text.that.contains('Edit:');

        page.checkClipboard();
    },
    'edit API credentials': (browser) => {
        const page = integrationPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw_integration_list__edit-action',
                scope: `${page.elements.gridRow}--0`
            })
            .expect.element(page.elements.modalTitle).to.have.text.that.contains('Edit:');

        page.changeApiCredentials();
    },
    'verify edited API credentials': (browser) => {
        const page = integrationPage(browser);

        browser
            .waitForElementNotPresent(page.elements.modal)
            .waitForElementPresent(page.elements.listColumnName)
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw_integration_list__edit-action',
                scope: `${page.elements.gridRow}--0`
            })
            .waitForElementVisible(page.elements.integrationName);

        page.verifyChangedApiCredentials();
    }
};
