const ruleBuilderPage = require('administration/page-objects/module/sw-rule.page-object.js');

module.exports = {
    '@tags': ['settings', 'rule', 'edit', 'rule-condition', 'nested-condition', 'condition-types'],
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
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-settings-rule')
            .assert.urlContains('#/sw/settings/rule/index');
    },
    'find rule to be edited': (browser) => {
        const page = ruleBuilderPage(browser);
        browser
            .fillGlobalSearchField(global.AdminFixtureService.basicFixture.name)
            .expect.element(`${page.elements.gridRow}--0 ${page.elements.columnName}`).to.have.text.that.contains(global.AdminFixtureService.basicFixture.name);
    },
    'edit rule and add first condition': (browser) => {
        const page = ruleBuilderPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-rule-list__rule-edit-action',
                scope: `${page.elements.gridRow}--0`
            })
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains(global.AdminFixtureService.basicFixture.name);

        page.createDateRangeCondition({
            type: 'Date range',
            ruleSelector: `${page.elements.conditionOrContainer}--0 ${page.elements.baseCondition}`,
            fromDate: '2019-28-03 12:12',
            toDate: '2019-28-04 12:12',
            useTime: true
        });
    },
    'add an and-condition as subcondition to the rule': (browser) => {
        const page = ruleBuilderPage(browser);

        browser.click('.sw-condition-and-container__actions--and')
            .waitForElementVisible('.condition-content__spacer--and')
            .waitForElementVisible(`${page.elements.conditionOrContainer}--0 ${page.elements.conditionAndContainer}--1`);

        page.createBasicInputCondition({
            type: 'Cart amount',
            inputName: 'amount',
            operator: 'Greater',
            ruleSelector: `${page.elements.conditionOrContainer}--0 ${page.elements.conditionAndContainer}--1 ${page.elements.baseCondition}`,
            value: '100'
        });
    },
    'add another and-condition before the second one': (browser) => {
        const page = ruleBuilderPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-condition-base__create-before-action',
                scope: `${page.elements.conditionAndContainer}--1`
            });

        page.createBasicSelectCondition({
            type: 'Customer group',
            operator: 'Is none of',
            ruleSelector: `${page.elements.conditionOrContainer}--0 ${page.elements.conditionAndContainer}--1 ${page.elements.baseCondition}`,
            value: 'Standard customer group',
            isMulti: true
        });

        browser.expect.element(`${page.elements.conditionAndContainer}--1 ${page.elements.ruleFieldCondition}`).to.have.attribute('title').that.equals('Customer group');
    },
    'add another and-condition to the rule after the second one': (browser) => {
        const page = ruleBuilderPage(browser);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-condition-base__create-after-action',
                scope: `${page.elements.conditionAndContainer}--1`
            });

        page.createBasicSelectCondition({
            type: 'Billing country',
            operator: 'Is none of',
            ruleSelector: `${page.elements.conditionOrContainer}--0 ${page.elements.conditionAndContainer}--2 ${page.elements.baseCondition}`,
            value: 'Australia',
            isMulti: true
        });

        browser.expect.element(`${page.elements.conditionAndContainer}--2 ${page.elements.ruleFieldCondition}`).to.have.attribute('title').that.equals('Billing country');
    },
    'create second main condition as or-condition': (browser) => {
        const page = ruleBuilderPage(browser);

        browser
            .click('.sw-condition-or-container__actions--or')
            .waitForElementVisible(page.elements.orSpacer);

        page.createBasicSelectCondition({
            type: 'Is new customer',
            ruleSelector: `${page.elements.conditionOrContainer}--1 ${page.elements.baseCondition}`,
            value: 'No',
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
            type: 'Last name',
            inputName: 'lastName',
            operator: 'Not equals',
            ruleSelector: `${page.elements.conditionOrContainer}--1 ${page.elements.conditionOrContainer}--0 ${page.elements.baseCondition}`,
            value: 'Norris'
        });

        browser
            .click(`${page.elements.conditionOrContainer}--1 .sw-condition-or-container__actions--or`)
            .waitForElementVisible(`${page.elements.conditionOrContainer}--1 ${page.elements.conditionOrContainer}--1`)
            .waitForElementVisible(`${page.elements.conditionOrContainer}--1 ${page.elements.subConditionContainer} ${page.elements.orSpacer}`)
            .getLocationInView(`${page.elements.conditionOrContainer}--1 ${page.elements.andSpacer}`);

        page.createCombinedInputSelectCondition({
            type: 'Line item with quantity',
            ruleSelector: `${page.elements.conditionOrContainer}--1 ${page.elements.conditionOrContainer}--1 ${page.elements.baseCondition}`,
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
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-context-menu-item--danger',
                scope: `${page.elements.conditionOrContainer}--1 ${page.elements.conditionOrContainer}--1`
            })
            .waitForElementNotPresent(`${page.elements.conditionOrContainer}--1 ${page.elements.conditionOrContainer}--1 ${page.elements.baseCondition}`);
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
    }
};
