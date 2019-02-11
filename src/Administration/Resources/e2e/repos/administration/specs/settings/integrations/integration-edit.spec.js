const integrationPage = require('../../../page-objects/module/sw-integration.page-object.js');

module.exports = {
    '@tags': ['settings', 'integration-edit', 'integration', 'edit'],
    before: (browser, done) => {
        global.IntegrationFixtureService.setIntegrationFixtures().then(() => {
            done();
        });
    },
    'open integration module and look for the integration to be edited': (browser) => {
        const page = integrationPage(browser);

        browser
            .openMainMenuEntry({
                mainMenuPath: '#/sw/settings/index',
                menuTitle: 'Settings',
                index: 6,
                subMenuItemPath: '#/sw/integration/index',
                subMenuTitle: 'Integrations'
            })
            .waitForElementVisible(page.elements.listHeadline)
            .assert.containsText(page.elements.listHeadline, 'Welcome to the integration management')
            .waitForElementPresent(page.elements.listColumnName)
            .assert.containsText(`${page.elements.gridRow}--0 ${page.elements.listColumnName}`, global.IntegrationFixtureService.integrationFixture.name);
    },
    'edit integration': (browser) => {
        const page = integrationPage(browser);

        browser
            .click(page.elements.contextMenuButton)
            .waitForElementVisible(`body > ${page.elements.contextMenu}`)
            .waitForElementVisible('.sw_integration_list__edit-action')
            .click('.sw_integration_list__edit-action')
            .waitForElementVisible(page.elements.modalTitle)
            .assert.containsText('.sw-modal__title', 'Integration')
            .fillField(page.elements.integrationName, 'Once again: Edits integration', true)
            .click(page.elements.integrationSaveAction)
            .checkNotification('Integration has been saved successfully')
            .assert.urlContains('#/sw/integration/index');
    },
    'verify saving of edited integration': (browser) => {
        const page = integrationPage(browser);

        browser
            .refresh()
            .waitForElementPresent(page.elements.listColumnName)
            .click(page.elements.contextMenuButton)
            .waitForElementVisible(`body > ${page.elements.contextMenu}`)
            .waitForElementVisible('.sw_integration_list__edit-action')
            .click('.sw_integration_list__edit-action')
            .waitForElementVisible(page.elements.modalTitle)
            .waitForElementVisible(page.elements.integrationName)
            .expect.element(page.elements.integrationName).value.to.equal('Once again: Edits integration');
    },
    'close modal without saving': (browser) => {
        const page = integrationPage(browser);

        browser
            .waitForElementPresent(page.elements.listColumnName)
            .click(`${page.elements.modal}__close`)
            .waitForElementNotPresent(page.elements.modal)
            .waitForElementPresent(page.elements.listColumnName)
            .assert.containsText(`${page.elements.gridRow}--0 ${page.elements.listColumnName}`, 'Once again: Edits integration');
    },
    after: (browser) => {
        browser.end();
    }
};
