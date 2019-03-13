const ruleBuilderPage = require('administration/page-objects/module/sw-rule.page-object.js');

module.exports = {
    '@tags': ['settings', 'rule', 'rule-edit', 'edit'],
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
    'find rule to be edited': (browser) => {
        const page = ruleBuilderPage(browser);

        browser.expect.element(page.elements.columnName).to.have.text.that.contains(global.AdminFixtureService.basicFixture.name);
    },
    'edit rule and add conditions': (browser) => {
        const page = ruleBuilderPage(browser);

        browser
            .clickContextMenuItem('.sw-rule-list__rule-edit-action', page.elements.contextMenuButton)
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains(global.AdminFixtureService.basicFixture.name);

        page.createBasicSelectCondition({
            type: 'Currency',
            operator: 'Is none of',
            ruleSelector: `${page.elements.conditionOrContainer}--0`,
            value: 'Euro',
            isMulti: true
        });

        browser
            .fillField('input[name=sw-field--rule-name]', 'Ediths rule', true)
            .click('.sw-settings-rule-detail__save-action')
            .checkNotification('The rule "Ediths rule" has been saved successfully.');
    },
    'verify changed rule': (browser) => {
        const page = ruleBuilderPage(browser);

        browser
            .click(page.elements.smartBarBack)
            .waitForElementNotPresent(page.elements.loader)
            .expect.element(page.elements.columnName).to.have.text.that.contains('Ediths rule');
    },
    after: (browser) => {
        browser.end();
    }
};
