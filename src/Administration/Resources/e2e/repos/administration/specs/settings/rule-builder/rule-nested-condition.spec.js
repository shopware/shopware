const ruleBuilderPage = require('administration/page-objects/module/sw-rule.page-object.js');

module.exports = {
    '@tags': ['settings', 'rule', 'rule-nested-condition', 'nested-condition', 'conditions'],
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
    'find rule to be edited': (browser) => {
        const page = ruleBuilderPage(browser);
        browser.expect.element(page.elements.columnName).to.have.text.that.contains(global.AdminFixtureService.basicFixture.name);
    },
    'edit rule and add first condition': (browser) => {
        const page = ruleBuilderPage(browser);

        browser
            .clickContextMenuItem('.sw-rule-list__rule-edit-action', page.elements.contextMenuButton, `${page.elements.gridRow}--0`)
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains(global.AdminFixtureService.basicFixture.name);

        page.createBasicSelectCondition(
            'currency',
            'Is none of',
            `${page.elements.conditionOrContainer}--0`,
            'Euro'
        );
    },
    'add an and-condition as subcondition to the rule': (browser) => {
        const page = ruleBuilderPage(browser);

        browser.click('.sw-condition-and-container__actions--and');

        page.createBasicSelectCondition(
            'shipping country',
            'Is none of',
            `${page.elements.conditionOrContainer}--0 ${page.elements.conditionAndContainer}--1`,
            'Australia'
        );
    },
    'add another and-condition before the second one': (browser) => {
        const page = ruleBuilderPage(browser);

        browser.clickContextMenuItem('.sw-rule-list__create-before-action', page.elements.contextMenuButton, `${page.elements.conditionAndContainer}--1`);

        page.createBasicSelectCondition(
            'customer group',
            'Is none of',
            `${page.elements.conditionOrContainer}--0 ${page.elements.conditionAndContainer}--1`,
            'Standard customer group'
        );

        browser.expect.element(`${page.elements.conditionAndContainer}--1 ${page.elements.ruleFieldCondition}`).to.have.attribute('title').that.equals('customer group');
    },
    'add another and-condition to the rule after the second one': (browser) => {
        const page = ruleBuilderPage(browser);

        browser.clickContextMenuItem('.sw-rule-list__create-after-action', page.elements.contextMenuButton, `${page.elements.conditionAndContainer}--1`);

        page.createBasicSelectCondition(
            'billing country',
            'Is none of',
            `${page.elements.conditionOrContainer}--0 ${page.elements.conditionAndContainer}--2`,
            'Australia'
        );

        browser.expect.element(`${page.elements.conditionAndContainer}--2 ${page.elements.ruleFieldCondition}`).to.have.attribute('title').that.equals('billing country');
    },
    'create second one as or-condition': (browser) => {
        const page = ruleBuilderPage(browser);

        browser
            .click('.sw-condition-or-container__actions--or')
            .waitForElementVisible(page.elements.orSpacer);

        page.createBasicSelectCondition(
            'currency',
            'Is none of',
            `${page.elements.conditionOrContainer}--1`,
            'Euro'
        );
    },
    'add an or-condition as sub condition to that second one': (browser) => {
        const page = ruleBuilderPage(browser);

        browser
            .click(`${page.elements.conditionOrContainer}--1 .sw-condition-and-container__actions--sub`)
            .waitForElementVisible(`${page.elements.conditionOrContainer}--1 ${page.elements.conditionAndContainer}--1`)
            .getLocationInView(`${page.elements.conditionOrContainer}--1 ${page.elements.subConditionContainer}`);

        page.createBasicSelectCondition('currency',
            'Is none of',
            `${page.elements.conditionOrContainer}--1 ${page.elements.conditionOrContainer}--0`,
            'Euro'
        );

        browser
            .click(`${page.elements.conditionOrContainer}--1 .sw-condition-or-container__actions--or`)
            .waitForElementVisible(`${page.elements.conditionOrContainer}--1 ${page.elements.conditionOrContainer}--1`)
            .waitForElementVisible(`${page.elements.conditionOrContainer}--1 ${page.elements.subConditionContainer} ${page.elements.orSpacer}`)
            .getLocationInView(`${page.elements.conditionOrContainer}--1 ${page.elements.andSpacer}`);

        page.createBasicSelectCondition('currency',
            'Is none of',
            `${page.elements.conditionOrContainer}--1 ${page.elements.conditionOrContainer}--1`,
            'Euro'
        );
    },
    'save rule with nested condition': (browser) => {
        const page = ruleBuilderPage(browser);

        browser
            .click(page.elements.ruleSaveAction)
            .checkNotification(`The rule "${global.AdminFixtureService.basicFixture.name}" has been saved successfully.`);
    },
    'delete single condition': (browser) => {
        const page = ruleBuilderPage(browser);

        browser
            .getLocationInView('.sw-condition-container__or-child--1')
            .clickContextMenuItem('.sw-context-menu-item--danger', page.elements.contextMenuButton, `${page.elements.conditionOrContainer}--1 ${page.elements.conditionOrContainer}--1`)
            .waitForElementNotPresent(`${page.elements.conditionOrContainer}--1 ${page.elements.conditionOrContainer}--1`);
    },
    'delete single container': (browser) => {
        const page = ruleBuilderPage(browser);

        browser
            .click(`${page.elements.conditionOrContainer}--1 ${page.elements.ruleDeleteAction}`)
            .waitForElementNotPresent(`${page.elements.conditionOrContainer}--1 ${page.elements.subConditionContainer}`);
    },
    'delete all containers': (browser) => {
        const page = ruleBuilderPage(browser);

        browser
            .click(page.elements.ruleDeleteAction)
            .expect.element(page.elements.ruleFieldCondition).to.have.attribute('title').that.equals('Placeholder');
        browser
            .click(page.elements.ruleSaveAction)
            .checkNotification(`An error occurred while saving rule "${global.AdminFixtureService.basicFixture.name}".`)
            .expect.element('.sw-condition-base__error-container').to.have.text.that.equals('type: This "type" value (placeholder) is invalid.');
    },
    after: (browser) => {
        browser.end();
    }
};
