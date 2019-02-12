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
            .waitForElementVisible(page.elements.listHeadline)
            .assert.containsText(page.elements.listHeadline, 'Welcome to the integration management')
            .assert.urlContains('#/sw/integration/index')
            .waitForElementPresent(page.elements.listColumnName)
            .assert.containsText(`${page.elements.gridRow}--0 ${page.elements.listColumnName}`, global.IntegrationFixtureService.integrationFixture.name);
    },
    'check the clipboard': (browser) => {
        const page = integrationPage(browser);

        browser
            .clickContextMenuItem(`.sw_integration_list__edit-action`, page.elements.contextMenuButton, `${page.elements.gridRow}--0`)
            .waitForElementVisible(page.elements.modalTitle)
            .assert.containsText(page.elements.modalTitle, 'Integration');

        page.checkClipboard();
    },
    'edit API credentials': (browser) => {
        const page = integrationPage(browser);

        browser
            .clickContextMenuItem(`.sw_integration_list__edit-action`, page.elements.contextMenuButton, `${page.elements.gridRow}--0`)
            .waitForElementVisible(page.elements.modalTitle)
            .assert.containsText(page.elements.modalTitle, 'Integration');

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
