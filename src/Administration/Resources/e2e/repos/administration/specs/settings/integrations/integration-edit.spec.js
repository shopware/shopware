module.exports = {
    '@tags': ['integration-edit', 'integration', 'edit'],
    before: (browser, done) => {
        global.IntegrationFixtureService.setIntegrationFixtures().then(() => {
            done();
        });
    },
    'open integration module and look for the integration to be edited': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/integration/index', 'Integrations')
            .waitForElementVisible('.sw-integration-list__welcome-headline')
            .assert.containsText('.sw-integration-list__welcome-headline', 'Welcome to the integration management')
            .waitForElementPresent('.sw-integration-list__column-integration-name')
            .assert.containsText('.sw-integration-list__column-integration-name .sw-grid__cell-content', global.IntegrationFixtureService.integrationFixture.name);
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
    after: (browser) => {
        browser.end();
    }
};
