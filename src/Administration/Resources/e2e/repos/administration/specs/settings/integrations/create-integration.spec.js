const integrationPage = require('../../../page-objects/sw-integration.page-object.js');

module.exports = {
    '@tags': ['integration-create','integration', 'create'],
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
            .fillField('input[name=sw-field--currentIntegration-label]', 'My very own integration')
            .tickCheckbox('input[name=sw-field--currentIntegration-writeAccess]','on')
            .waitForElementVisible('.sw-field__copy-button')
            .click('.sw-field__copy-button')
            .checkNotification('Text has been copied to clipboard')
            .waitForElementPresent('.sw-integration-detail-modal__save-action')
            .click('.sw-integration-detail-modal__save-action')
            .waitForElementVisible('.sw-notifications .sw-alert')
            .assert.containsText('.sw-alert .sw-alert__message', 'Integration has been saved successfully')
            .assert.urlContains('#/sw/integration/index');
    },
    'verify newly created integration': (browser) => {
        browser
            .waitForElementPresent('.sw-integration-list__column-integration-name')
            .assert.containsText('.sw-integration-list__column-integration-name .sw-grid__cell-content', 'My very own integration')
            .click('.sw-context-button__button')
            .waitForElementVisible('body > .sw-context-menu');
    },
    'delete integration and verify deletion': (browser) => {
        const page = integrationPage(browser);
        page.deleteSingleIntegration('My very own integration');
    },
    after: (browser) => {
        browser.end();
    }
};
