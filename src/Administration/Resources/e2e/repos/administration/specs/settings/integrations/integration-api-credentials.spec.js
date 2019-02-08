const integrationPage = require('../../../page-objects/module/sw-integration.page-object.js');

module.exports = {
    '@tags': ['settings', 'integration-api-credentials', 'integration', 'api-credentials'],
    before: (browser, done) => {
        global.IntegrationFixtureService.setIntegrationFixtures().then(() => {
            done();
        });
    },
    'open integration module and look for the integration to be edited': (browser) => {
        const page = integrationPage(browser);

        browser
            .openMainMenuEntry({
                mainMenuPath: '#/sw/settings/index',
                menuTitle: 'Settings',
                index: 5,
                subMenuItemPath: '#/sw/integration/index',
                subMenuTitle: 'Integrations'
            })
            .expect.element(page.elements.listHeadline).to.have.text.that.contains('Welcome to the integration management');

        browser
            .assert.urlContains('#/sw/integration/index')
            .expect.element(`${page.elements.listColumnName} .sw-grid__cell-content`).to.have.text.that.contains(global.IntegrationFixtureService.integrationFixture.name).before(browser.globals.waitForConditionTimeout);
    },
    'check the clipboard': (browser) => {
        const page = integrationPage(browser);

        browser
            .clickContextMenuItem('.sw_integration_list__edit-action', page.elements.contextMenuButton)
            .expect.element(page.elements.modalTitle).to.have.text.that.contains('Integration').before(browser.globals.waitForConditionTimeout);

        page.checkClipboard();
    },
    'edit API credentials': (browser) => {
        const page = integrationPage(browser);

        browser
            .clickContextMenuItem('.sw_integration_list__edit-action', page.elements.contextMenuButton)
            .expect.element(page.elements.modalTitle).to.have.text.that.contains('Integration').before(browser.globals.waitForConditionTimeout);

        page.changeApiCredentials();
    },
    'verify edited API credentials': (browser) => {
        const page = integrationPage(browser);

        browser
            .waitForElementNotPresent(page.elements.modal)
            .waitForElementPresent(page.elements.listColumnName)
            .clickContextMenuItem(`.sw_integration_list__edit-action`, page.elements.contextMenuButton, `${page.elements.gridRow}--0`)
            .waitForElementVisible(page.elements.integrationName);

        page.verifyChangedApiCredentials();
    },
    after: (browser) => {
        browser.end();
    }
};
