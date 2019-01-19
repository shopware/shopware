const ruleBuilderPage = require('administration/page-objects/sw-rule.page-object.js');

module.exports = {
    '@tags': ['settings', 'rule', 'rule-create', 'create'],
    '@disabled': !global.flags.isActive('next516'),
    'navigate to rule index': (browser) => {
        browser
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/rule/index', 'Rules');
    },
    'create new rule with basic condition': (browser) => {
        const page = ruleBuilderPage(browser);

        browser
            .click('a[href="#/sw/settings/rule/create"]')
            .waitForElementVisible('.sw-settings-rule-detail .sw-card__content')
            .assert.urlContains('#/sw/settings/rule/create')
            .assert.containsText('.smart-bar__header h2', 'New rule');

        page.createBasicRule('Rule 1st');
    },
    'verify and search the new rule': (browser) => {
        const page = ruleBuilderPage(browser);

        browser
            .click('a.smart-bar__back-btn')
            .waitForElementNotPresent('.sw-loader')
            .waitForElementVisible(page.elements.columnName)
            .assert.containsText(page.elements.columnName, 'Rule 1st')
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/rule/index', 'Rules')
            .fillGlobalSearchField('Rule them all')
            .waitForElementVisible('.sw-page__smart-bar-amount')
            .assert.containsText('.sw-page__smart-bar-amount', '(1)');
    },
    after: (browser) => {
        browser.end();
    }
};
