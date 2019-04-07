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
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-integration')
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains('Integrations');
        browser.assert.urlContains('#/sw/integration/index');
    },
    'delete integration and verify deletion': (browser) => {
        const page = integrationPage(browser);
        page.deleteSingleIntegration(global.IntegrationFixtureService.integrationFixture.name);
    }
};
