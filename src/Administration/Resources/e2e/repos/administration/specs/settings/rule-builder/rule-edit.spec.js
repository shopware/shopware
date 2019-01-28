const ruleBuilderPage = require('administration/page-objects/sw-rule.page-object.js');

module.exports = {
    '@tags': ['settings', 'rule', 'rule-edit', 'edit'],
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
    'find rule to be edited': (browser) => {
        const page = ruleBuilderPage(browser);

        browser
            .waitForElementVisible(page.elements.columnName)
            .assert.containsText('.sw-settings-rule-list__column-name', global.FixtureService.basicFixture.name);
    },
    'edit rule and add conditions': (browser) => {
        const page = ruleBuilderPage(browser);

        browser
            .clickContextMenuItem('.sw-rule-list__rule-edit-action', '.sw-context-button__button')
            .waitForElementVisible('.sw-settings-rule-detail .sw-card__content')
            .assert.containsText('.smart-bar__header h2', global.FixtureService.basicFixture.name);

        page.createBasicSelectCondition('currency', 'Is one of', 'div[name=currencyIds]', 'Euro');

        browser
            .fillField('input[name=sw-field--rule-name]', 'Ediths rule', true)
            .waitForElementVisible('.sw-settings-rule-detail__save-action')
            .click('.sw-settings-rule-detail__save-action')
            .checkNotification('The rule "Ediths rule" was saved.');
    },
    'verify changed rule': (browser) => {
        const page = ruleBuilderPage(browser);

        browser
            .click('a.smart-bar__back-btn')
            .waitForElementNotPresent('.sw-loader')
            .waitForElementVisible(page.elements.columnName)
            .assert.containsText(page.elements.columnName, 'Ediths rule');
    },
    after: (browser) => {
        browser.end();
    }
};