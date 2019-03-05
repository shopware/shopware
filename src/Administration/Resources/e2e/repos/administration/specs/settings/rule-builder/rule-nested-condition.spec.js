const ruleBuilderPage = require('administration/page-objects/module/sw-rule.page-object.js');

module.exports = {
    '@tags': ['settings', 'rule', 'rule-nested-condition', 'conditions', 'nested-condition', 'condition-types'],
    '@disabled': !global.flags.isActive('next516'),
    before: (browser, done) => {
        global.AdminFixtureService.create('rule').then(() => {
            return global.ProductFixtureService.setProductFixture();
        }).then(() => {
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

        page.createDateRangeCondition({
            type: 'date range',
            ruleSelector: `${page.elements.conditionOrContainer}--0`,
            fromDate: '2019-03-28 12:12',
            toDate: '2019-04-28 12:12',
            useTime: true
        });
    },
    'add an and-condition as subcondition to the rule': (browser) => {
        const page = ruleBuilderPage(browser);

        browser.click('.sw-condition-and-container__actions--and');

        page.createBasicInputCondition({
            type: 'cart amount',
            inputName: 'amount',
            operator: 'Greater',
            ruleSelector: `${page.elements.conditionOrContainer}--0 ${page.elements.conditionAndContainer}--1`,
            value: '100'
        });
    },
    'add another and-condition before the second one': (browser) => {
        const page = ruleBuilderPage(browser);

        browser.clickContextMenuItem('.sw-condition-base__create-before-action', page.elements.contextMenuButton, `${page.elements.conditionAndContainer}--1`);

        page.createBasicSelectCondition({
            type: 'customer group',
            operator: 'Is none of',
            ruleSelector: `${page.elements.conditionOrContainer}--0 ${page.elements.conditionAndContainer}--1`,
            value: 'Standard customer group',
            isMulti: true
        });

        browser.expect.element(`${page.elements.conditionAndContainer}--1 ${page.elements.ruleFieldCondition}`).to.have.attribute('title').that.equals('customer group');
    },
    'add another and-condition to the rule after the second one': (browser) => {
        const page = ruleBuilderPage(browser);

        browser.clickContextMenuItem('.sw-condition-base__create-after-action', page.elements.contextMenuButton, `${page.elements.conditionAndContainer}--1`);

        page.createBasicSelectCondition({
            type: 'billing country',
            operator: 'Is none of',
            ruleSelector: `${page.elements.conditionOrContainer}--0 ${page.elements.conditionAndContainer}--2`,
            value: 'Australia',
            isMulti: true
        });

        browser.expect.element(`${page.elements.conditionAndContainer}--2 ${page.elements.ruleFieldCondition}`).to.have.attribute('title').that.equals('billing country');
    },
    'create second main condition as or-condition': (browser) => {
        const page = ruleBuilderPage(browser);

        browser
            .click('.sw-condition-or-container__actions--or')
            .waitForElementVisible(page.elements.orSpacer);

        page.createBasicSelectCondition({
            type: 'is new customer',
            ruleSelector: `${page.elements.conditionOrContainer}--1`,
            value: 'no',
            isMulti: false
        });
    },
    'add an or-condition as sub condition to that second one': (browser) => {
        const page = ruleBuilderPage(browser);

        browser
            .click(`${page.elements.conditionOrContainer}--1 .sw-condition-and-container__actions--sub`)
            .waitForElementVisible(`${page.elements.conditionOrContainer}--1 ${page.elements.conditionAndContainer}--1`)
            .getLocationInView(`${page.elements.conditionOrContainer}--1 ${page.elements.subConditionContainer}`);

        page.createBasicInputCondition({
            type: 'last name',
            inputName: 'lastName',
            operator: 'Not equals',
            ruleSelector: `${page.elements.conditionOrContainer}--1 ${page.elements.conditionOrContainer}--0`,
            value: 'Norris'
        });

        browser
            .click(`${page.elements.conditionOrContainer}--1 .sw-condition-or-container__actions--or`)
            .waitForElementVisible(`${page.elements.conditionOrContainer}--1 ${page.elements.conditionOrContainer}--1`)
            .waitForElementVisible(`${page.elements.conditionOrContainer}--1 ${page.elements.subConditionContainer} ${page.elements.orSpacer}`)
            .getLocationInView(`${page.elements.conditionOrContainer}--1 ${page.elements.andSpacer}`);

        page.createCombinedInputSelectCondition({
            type: 'line item with quantity',
            ruleSelector: `${page.elements.conditionOrContainer}--1 ${page.elements.conditionOrContainer}--1`,
            firstValue: global.ProductFixtureService.productFixture.name,
            secondValue: '20',
            inputName: 'quantity',
            operator: 'Greater',
            isMulti: false
        });
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
            .expect.element(page.elements.ruleFieldCondition).to.have.attribute('title').that.equals('');

        browser
            .click(page.elements.ruleSaveAction)
            .checkNotification(`An error occurred while saving rule "${global.AdminFixtureService.basicFixture.name}".`)
            .expect.element('.sw-condition-base__error-container').to.have.text.that.equals('This "type" value (NULL) is invalid.');
    },
    after: (browser) => {
        browser.end();
    }
};
