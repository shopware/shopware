const integrationPage = require('../../../page-objects/sw-integration.page-object.js');

module.exports = {
    '@tags': ['integration', 'create'],
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
            .setValue('input[name=sw-field--currentIntegration-label]', 'My very own integration')
            .click('input[name=sw-field--currentIntegration-writeAccess]')
            .waitForElementVisible('.sw-field__copy-button')
            .click('.sw-field__copy-button')
            .waitForElementVisible('.sw-notifications .sw-alert')
            .assert.containsText('.sw-alert .sw-alert__message', 'The text has been copied to clipboard')
            .click('.sw-alert .sw-alert__close')
            .waitForElementNotPresent('.sw-notifications .sw-alert')
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
