const ruleBuilderPage = require('administration/page-objects/module/sw-rule.page-object.js');

module.exports = {
    '@tags': ['settings', 'rule', 'rule-delete', 'delete'],
    '@disabled': !global.flags.isActive('next516'),
    before: (browser, done) => {
        global.AdminFixtureService.create('rule').then(() => {
            done();
        });
    },
    'navigate to rule index': (browser) => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/rule/index',
                mainMenuId: 'sw-settings',
                subMenuId: 'sw-settings-rule'
            });
    },
    'find rule to be deleted': (browser) => {
        const page = ruleBuilderPage(browser);

        browser.expect.element(page.elements.columnName).to.have.text.that.contains(global.AdminFixtureService.basicFixture.name);
    },
    'delete rule': (browser) => {
        const page = ruleBuilderPage(browser);

        browser
            .waitForElementVisible(page.elements.columnName)
            .assert.containsText(page.elements.columnName, global.AdminFixtureService.basicFixture.name)
            .clickContextMenuItem('.sw-context-menu-item--danger', '.sw-context-button__button').waitForElementVisible('.sw-modal')
            .assert.containsText('.sw-settings-rule-list__confirm-delete-text', `Are you sure you want to delete the rule "${global.AdminFixtureService.basicFixture.name}"?`)
            .click('.sw-modal__footer button.sw-button--primary')
            .waitForElementNotPresent('.sw-modal')
            .waitForElementVisible(page.elements.emptyState)
            .expect.element(page.elements.smartBarAmount).to.have.text.that.contains('(0)');
    },
    after: (browser) => {
        browser.end();
    }
};
