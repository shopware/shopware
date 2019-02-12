const ruleBuilderPage = require('administration/page-objects/module/sw-rule.page-object.js');

module.exports = {
    '@tags': ['settings', 'rule', 'rule-create', 'create'],
    '@disabled': !global.flags.isActive('next516'),
    'navigate to rule index': (browser) => {
        browser
            .openMainMenuEntry({
                mainMenuPath: '#/sw/settings/index',
                menuTitle: 'Settings',
                index: 5,
                subMenuItemPath: '#/sw/settings/rule/index',
                subMenuTitle: 'Rules'
            });
    },
    'create new rule with basic condition': (browser) => {
        const page = ruleBuilderPage(browser);

        browser
            .click('a[href="#/sw/settings/rule/create"]')
            .waitForElementVisible('.sw-settings-rule-detail .sw-card__content')
            .assert.urlContains('#/sw/settings/rule/create')
            .assert.containsText(`${page.elements.smartBarHeader} h2`, 'New rule');

        page.createBasicRule('Rule 1st');
    },
    'verify and search the new rule': (browser) => {
        const page = ruleBuilderPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .waitForElementNotPresent(page.elements.loader)
            .waitForElementVisible(page.elements.columnName)
            .assert.containsText(page.elements.columnName, 'Rule 1st')
            .openMainMenuEntry('#/sw/settings/index', 'Settings', '#/sw/settings/rule/index', 'Rules')
            .fillGlobalSearchField('Rule them all')
            .waitForElementVisible(page.elements.smartBarAmount)
            .assert.containsText(page.elements.smartBarAmount, '(1)');
    },
    after: (browser) => {
        browser.end();
    }
};
