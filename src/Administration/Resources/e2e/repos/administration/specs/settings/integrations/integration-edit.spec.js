const integrationPage = require('administration/page-objects/sw-integration.page-object.js');

module.exports = {
    '@tags': ['integration-edit','integration', 'edit'],
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
            .fillField('input[name=sw-field--currentIntegration-label]', 'Edits integration')
            .click('input[name=sw-field--currentIntegration-writeAccess]')
            .waitForElementPresent('.sw-integration-detail-modal__save-action')
            .click('.sw-integration-detail-modal__save-action')
            .checkNotification('Integration has been saved successfully')
            .assert.urlContains('#/sw/integration/index');
    },
    'verify newly created integration': (browser) => {
        browser
            .refresh()
            .waitForElementPresent('.sw-integration-list__column-integration-name')
            .assert.containsText('.sw-integration-list__column-integration-name .sw-grid__cell-content', 'Edits integration');
    },
    'edit integration': (browser) => {
        browser
            .click('.sw-context-button__button')
            .waitForElementVisible('body > .sw-context-menu')
            .waitForElementVisible('.sw_integration_list__edit-action')
            .click('.sw_integration_list__edit-action')
            .waitForElementVisible('.sw-modal__title')
            .assert.containsText('.sw-modal__title', 'Integration')
            .fillField('input[name=sw-field--currentIntegration-label]', 'Once again: Edits integration')
            .click('.sw-integration-detail-modal__save-action')
            .checkNotification('Integration has been saved successfully')
            .assert.urlContains('#/sw/integration/index');
    },
    'verify saving of edited integration': (browser) => {
        browser
            .refresh()
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
