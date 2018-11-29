const integrationPage = require('../../../page-objects/sw-integration.page-object.js');

module.exports = {
    '@tags': ['integration-api-credentials', 'integration', 'api-credentials'],
    'open integration module': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/integration/index', 'Integrations');
    },
    'go to create integration page': (browser) => {
        browser
            .waitForElementVisible('.sw-integration-list__welcome-headline')
            .assert.containsText('.sw-integration-list__welcome-headline', 'Welcome to the integration management')
            .waitForElementVisible('.sw-integration-list__add-integration-action')
            .click('.sw-integration-list__add-integration-action');
    },
    'create and save integration': (browser) => {
        browser
            .waitForElementVisible('.sw-modal__title')
            .assert.containsText('.sw-modal__title', 'Integration')
            .fillField('input[name=sw-field--currentIntegration-label]', 'Wonderful API integration example')
            .tickCheckbox('input[name=sw-field--currentIntegration-writeAccess]','on')
            .waitForElementPresent('.sw-integration-detail-modal__save-action')
            .click('.sw-integration-detail-modal__save-action')
            .waitForElementVisible('.sw-notifications .sw-alert')
            .assert.containsText('.sw-alert .sw-alert__message', 'Integration has been saved successfully')
            .assert.urlContains('#/sw/integration/index');
    },
    'verify newly created integration': (browser) => {
        browser
            .click('.sw-alert .sw-alert__close')
            .waitForElementNotPresent('.sw-notifications .sw-alert')
            .waitForElementPresent('.sw-integration-list__column-integration-name')
            .assert.containsText('.sw-integration-list__column-integration-name .sw-grid__cell-content', 'Wonderful API integration example');
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
            .click('.sw-alert .sw-alert__close')
            .waitForElementNotPresent('.sw-notification__alert')
            .waitForElementNotPresent('.sw-modal')
            .waitForElementPresent('.sw-integration-list__column-integration-name')
            .clickContextMenuItem('.sw_integration_list__edit-action', '.sw-context-button__button')
            .waitForElementVisible('input[name=sw-field--currentIntegration-label]');

        page.verifyChangedApiCredentials();
    },
    'delete integration and verify deletion': (browser) => {
        const page = integrationPage(browser);
        page.deleteSingleIntegration('Wonderful API integration example');
    },
    after: (browser) => {
        browser.end();
    }
};
