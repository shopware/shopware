const integrationPage = require('../../../page-objects/module/sw-integration.page-object.js');

module.exports = {
    '@tags': ['settings', 'integration-delete', 'integration', 'delete'],
    before: (browser, done) => {
        global.IntegrationFixtureService.setIntegrationFixtures().then(() => {
            done();
        });
    },
    'open integration module and look for the integration to be deleted': (browser) => {
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

        browser.assert.urlContains('#/sw/integration/index')
    },
    'delete integration and verify deletion': (browser) => {
        const page = integrationPage(browser);
        page.deleteSingleIntegration(global.IntegrationFixtureService.integrationFixture.name);
    },
    after: (browser) => {
        browser.end();
    }
};
