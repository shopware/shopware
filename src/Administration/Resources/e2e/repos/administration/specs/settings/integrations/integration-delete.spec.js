const integrationPage = require('../../../page-objects/module/sw-integration.page-object.js');

module.exports = {
    '@tags': ['integration-delete', 'integration', 'delete'],
    before: (browser, done) => {
        global.IntegrationFixtureService.setIntegrationFixtures().then(() => {
            done();
        });
    },
    'open integration module and look for the integration to be deleted': (browser) => {
        const page = integrationPage(browser);
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/integration/index', 'Integrations')
            .waitForElementVisible(page.elements.listHeadline)
            .assert.containsText(page.elements.listHeadline, 'Welcome to the integration management')
            .assert.urlContains('#/sw/integration/index');
    },
    'delete integration and verify deletion': (browser) => {
        const page = integrationPage(browser);
        page.deleteSingleIntegration(global.IntegrationFixtureService.integrationFixture.name);
    },
    after: (browser) => {
        browser.end();
    }
};
