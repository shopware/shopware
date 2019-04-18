const integrationPage = require('../../../page-objects/module/sw-integration.page-object.js');

module.exports = {
    '@tags': ['settings', 'integration-create', 'integration', 'create'],
    'open integration module': (browser) => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-integration')
            .assert.urlContains('#/sw/integration/index');
    },
    'go to create integration page': (browser) => {
        const page = integrationPage(browser);

        browser.expect.element(page.elements.smartBarHeader).to.have.text.that.contains('Integrations');
    },
    'create and save integration': (browser) => {
        const page = integrationPage(browser);

        browser
            .click('.sw-integration-list__add-integration-action')
            .expect.element(page.elements.modalTitle).to.have.text.that.contains('Create integration');

        browser
            .fillField('input[name=sw-field--currentIntegration-label]', 'My very own integration')
            .tickCheckbox('input[name=sw-field--currentIntegration-writeAccess]', true)
            .click(page.elements.integrationSaveAction)
            .checkNotification('Integration has been saved successfully')
            .assert.urlContains('#/sw/integration/index');
    },
    'verify newly created integration': (browser) => {
        const page = integrationPage(browser);

        browser.expect.element(`${page.elements.gridRow}--0 ${page.elements.listColumnName}`).to.have.text.that.contains('My very own integration');
    }
};
