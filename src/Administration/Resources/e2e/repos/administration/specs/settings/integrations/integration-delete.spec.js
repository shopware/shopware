const integrationPage = require('../../../page-objects/sw-integration.page-object.js');
const integrationFixture = global.FixtureService.loadJson('integration.json');

module.exports = {
    '@tags': ['integration-delete', 'integration', 'delete'],
    before: (browser, done) => {
        global.FixtureService.create('/v1/integration', integrationFixture, 'integration', done);
        integrationFixture.name = 'My very own integration';
        integrationFixture.label = 'My very own integration';
    },
    'open integration module and look for the integration to be deleted': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/integration/index', 'Integrations')
            .waitForElementVisible('.sw-integration-list__welcome-headline')
            .assert.containsText('.sw-integration-list__welcome-headline', 'Welcome to the integration management')
            .assert.urlContains('#/sw/integration/index');
    },
    'delete integration and verify deletion': (browser) => {
        const page = integrationPage(browser);
        page.deleteSingleIntegration(integrationFixture.name);
    },
    after: (browser) => {
        browser.end();
    }
};
