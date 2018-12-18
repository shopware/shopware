const integrationPage = require('../../../page-objects/sw-integration.page-object.js');
const integrationFixture = global.FixtureService.loadJson('integration.json');

module.exports = {
    '@tags': ['integration-api-credentials', 'integration', 'api-credentials'],
    before: (browser, done) => {
        global.FixtureService.create('/v1/integration', integrationFixture, 'integration', done);
    },
    'open integration module and look for the integration to be edited': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/integration/index', 'Integrations')
            .waitForElementVisible('.sw-integration-list__welcome-headline')
            .assert.containsText('.sw-integration-list__welcome-headline', 'Welcome to the integration management')
            .assert.urlContains('#/sw/integration/index')
            .waitForElementPresent('.sw-integration-list__column-integration-name')
            .assert.containsText('.sw-integration-list__column-integration-name .sw-grid__cell-content', integrationFixture.name);
    },
    'check the clipboard': (browser) => {
        const page = integrationPage(browser);

        browser
            .clickContextMenuItem('.sw_integration_list__edit-action', '.sw-context-button__button')
            .waitForElementVisible('.sw-modal__title')
            .assert.containsText('.sw-modal__title', 'Integration');

        page.checkClipboard();
    },
    'edit API credentials': (browser) => {
        const page = integrationPage(browser);

        browser
            .clickContextMenuItem('.sw_integration_list__edit-action', '.sw-context-button__button')
            .waitForElementVisible('.sw-modal__title')
            .assert.containsText('.sw-modal__title', 'Integration');

        page.changeApiCredentials();
    },
    'verify edited API credentials': (browser) => {
        const page = integrationPage(browser);

        browser
            .waitForElementNotPresent('.sw-modal')
            .waitForElementPresent('.sw-integration-list__column-integration-name')
            .clickContextMenuItem('.sw_integration_list__edit-action', '.sw-context-button__button')
            .waitForElementVisible('input[name=sw-field--currentIntegration-label]');

        page.verifyChangedApiCredentials();
    },
    after: (browser) => {
        browser.end();
    }
};
