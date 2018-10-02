const integrationPage = require('../../../page-objects/sw-integration.page-object.js');

module.exports = {
    '@tags': ['integration', 'api-credentials'],
    'open integration module': (browser) => {
        browser
            .assert.containsText('.sw-settings .collapsible-text', 'Settings')
            .click('.sw-admin-menu__navigation-link[href="#/sw/settings/index"]')
            .waitForElementVisible('.sw-settings-item[href="#/sw/integration/index"]')
            .click('.sw-settings-item[href="#/sw/integration/index"]');
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
            .setValue('input[name=sw-field--currentIntegration-label]', 'Wonderful API integration example')
            .click('input[name=sw-field--currentIntegration-writeAccess]')
            .waitForElementPresent('.sw-integration-detail-modal__save-action')
            .click('.sw-integration-detail-modal__save-action')
            .waitForElementVisible('.sw-notifications .sw-alert')
            .assert.containsText('.sw-alert .sw-alert__message', 'Integration saved successful')
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
            .click('.sw-context-button__button')
            .waitForElementVisible('body > .sw-context-menu')
            .waitForElementVisible('.sw_integration_list__edit-action')
            .click('.sw_integration_list__edit-action')
            .waitForElementVisible('.sw-modal__title')
            .assert.containsText('.sw-modal__title', 'Integration');

        page.checkClipboard();
    },
    'edit API credentials': (browser) => {
        const page = integrationPage(browser);

        browser
            .click('.sw-context-button__button')
            .waitForElementVisible('body > .sw-context-menu')
            .waitForElementVisible('.sw_integration_list__edit-action')
            .click('.sw_integration_list__edit-action')
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
            .click('.sw-context-button__button')
            .waitForElementVisible('body > .sw-context-menu')
            .waitForElementVisible('.sw_integration_list__edit-action')
            .click('.sw_integration_list__edit-action')
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
