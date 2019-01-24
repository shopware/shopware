const ruleBuilderPage = require('administration/page-objects/sw-rule.page-object.js');

module.exports = {
    '@tags': ['settings', 'rule', 'rule-delete', 'delete'],
    '@disabled': !global.flags.isActive('next516'),
    before: (browser, done) => {
        global.FixtureService.create('rule').then(() => {
            done();
        });
    },
    'navigate to rule index': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/rule/index', 'Rules');
    },
    'find rule to be deleted': (browser) => {
        const page = ruleBuilderPage(browser);

        browser
            .waitForElementVisible(page.elements.columnName)
            .assert.containsText('.sw-settings-rule-list__column-name', global.FixtureService.basicFixture.name);
    },
    'delete rule': (browser) => {
        const page = ruleBuilderPage(browser);

        browser
            .waitForElementVisible(page.elements.columnName)
            .assert.containsText(page.elements.columnName, global.FixtureService.basicFixture.name)
            .clickContextMenuItem('.sw-context-menu-item--danger', '.sw-context-button__button').waitForElementVisible('.sw-modal')
            .assert.containsText('.sw-settings-rule-list__confirm-delete-text', `Do you really want to delete the rule "${global.FixtureService.basicFixture.name}"?`)
            .click('.sw-modal__footer button.sw-button--primary')
            .waitForElementNotPresent('.sw-modal')
            .waitForElementNotPresent(page.elements.columnName)
            .waitForElementVisible('.sw-empty-state')
            .waitForElementVisible('.sw-page__smart-bar-amount')
            .assert.containsText('.sw-page__smart-bar-amount', '(0)');
    },
    after: (browser) => {
        browser.end();
    }
};