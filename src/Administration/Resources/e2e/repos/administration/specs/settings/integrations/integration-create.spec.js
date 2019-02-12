const integrationPage = require('../../../page-objects/module/sw-integration.page-object.js');

module.exports = {
    '@tags': ['settings', 'integration-create','integration', 'create'],
    'open integration module': (browser) => {
        browser
            .openMainMenuEntry({
                mainMenuPath: '#/sw/settings/index',
                menuTitle: 'Settings',
                index: 5,
                subMenuItemPath: '#/sw/integration/index',
                subMenuTitle: 'Integrations'
            });
    },
    'go to create integration page': (browser) => {
        const page = integrationPage(browser);

        browser
            .waitForElementVisible(page.elements.listHeadline)
            .assert.containsText(page.elements.listHeadline, 'Welcome to the integration management')
            .waitForElementVisible('.sw-integration-list__add-integration-action')
            .click('.sw-integration-list__add-integration-action');

    },
    'create and save integration': (browser) => {
        const page = integrationPage(browser);

        browser
            .waitForElementVisible(page.elements.modalTitle)
            .assert.containsText(page.elements.modalTitle, 'Integration')
            .fillField('input[name=sw-field--currentIntegration-label]', 'My very own integration')
            .tickCheckbox('input[name=sw-field--currentIntegration-writeAccess]','on')
            .waitForElementVisible('.sw-field__copy-button')
            .click('.sw-field__copy-button')
            .checkNotification('Text has been copied to clipboard')
            .waitForElementPresent(page.elements.integrationSaveAction)
            .click(page.elements.integrationSaveAction)
            .waitForElementVisible('.sw-notifications .sw-alert')
            .assert.containsText('.sw-alert .sw-alert__message', 'Integration has been saved successfully')
            .assert.urlContains('#/sw/integration/index');
    },
    'verify newly created integration': (browser) => {
        const page = integrationPage(browser);

        browser
            .waitForElementPresent(page.elements.listColumnName)
            .assert.containsText(`${page.elements.gridRow}--0 ${page.elements.listColumnName}`, 'My very own integration');
    },
    after: (browser) => {
        browser.end();
    }
};
