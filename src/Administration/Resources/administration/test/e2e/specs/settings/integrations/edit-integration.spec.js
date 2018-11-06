const integrationPage = require('../../../page-objects/sw-integration.page-object.js');

module.exports = {
    '@tags': ['integration-edit', 'integration', 'edit'],
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
            .setValue('input[name=sw-field--currentIntegration-label]', 'Edits integration')
            .click('input[name=sw-field--currentIntegration-writeAccess]')
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
            .assert.containsText('.sw-integration-list__column-integration-name .sw-grid__cell-content', 'Edits integration')
    },
    'edit integration': (browser) => {
        browser
            .click('.sw-context-button__button')
            .waitForElementVisible('body > .sw-context-menu')
            .waitForElementVisible('.sw_integration_list__edit-action')
            .click('.sw_integration_list__edit-action')
            .waitForElementVisible('.sw-modal__title')
            .assert.containsText('.sw-modal__title', 'Integration')
            .clearValue('input[name=sw-field--currentIntegration-label]')
            .setValue('input[name=sw-field--currentIntegration-label]', 'Once again: Edits integration')
            .click('.sw-integration-detail-modal__save-action')
            .waitForElementVisible('.sw-notifications .sw-alert')
            .assert.containsText('.sw-alert .sw-alert__message', 'Integration has been saved successfully')
            .assert.urlContains('#/sw/integration/index');
    },
    'verify saving of edited integration': (browser) => {
        browser
            .click('.sw-alert .sw-alert__close')
            .waitForElementNotPresent('.sw-notifications .sw-alert')
            .waitForElementPresent('.sw-integration-list__column-integration-name')
            .click('.sw-context-button__button')
            .waitForElementVisible('body > .sw-context-menu')
            .waitForElementVisible('.sw_integration_list__edit-action')
            .click('.sw_integration_list__edit-action')
            .waitForElementVisible('.sw-modal__title')
            .waitForElementVisible('input[name=sw-field--currentIntegration-label]')
            .expect.element('input[name=sw-field--currentIntegration-label]').value.to.equal('Once again: Edits integration');
    },
    'close modal without saving': (browser) => {
        browser
            .waitForElementPresent('.sw-integration-list__column-integration-name')
            .click('.sw-modal__close')
            .waitForElementNotPresent('.sw-modal')
            .waitForElementPresent('.sw-integration-list__column-integration-name')
            .assert.containsText('.sw-integration-list__column-integration-name .sw-grid__cell-content', 'Once again: Edits integration');
    },
    'delete integration and verify deletion': (browser) => {
        const page = integrationPage(browser);
        page.deleteSingleIntegration('Once again: Edits integration');
    },
    after: (browser) => {
        browser.end();
    }
};
