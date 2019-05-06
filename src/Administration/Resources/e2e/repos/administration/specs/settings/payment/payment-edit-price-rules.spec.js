const paymentPage = require('administration/page-objects/module/sw-payment.page-object.js');
const rulePage = require('administration/page-objects/module/sw-rule.page-object.js');

module.exports = {
    '@tags': ['settings', 'payment', 'payment-edit-availability', 'availability'],
    before: (browser, done) => {
        global.AdminFixtureService.create('payment-method').then(() => {
            done();
        });
    },
    'navigate to payment page': browser => {
        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-settings-payment')
            .assert.urlContains('#/sw/settings/payment/index');
    },
    'find payment method to be edited': browser => {
        const page = paymentPage(browser);

        browser
            .fillGlobalSearchField(global.AdminFixtureService.basicFixture.name)
            .waitForElementNotPresent(page.elements.loader)
            .expect.element(page.elements.smartBarAmount).to.have.text.that.equals('(1)');
        browser.expect.element(`${page.elements.gridRow}--0`).to.have.text.that.contains(global.AdminFixtureService.basicFixture.name);

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-context-menu-item:nth-of-type(1)',
                scope: `${page.elements.gridRow}--0`
            })
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains(global.AdminFixtureService.basicFixture.name);
    },
    'open modal for new availability rule': browser => {
        const page = paymentPage(browser);

        browser
            .waitForElementVisible('.sw-settings-payment-detail__condition_container .sw-select-rule-create')
            .click('.sw-settings-payment-detail__condition_container .sw-select-rule-create')
            .waitForElementVisible('.sw-select__results')
            .click('.sw-select-option---1')
            .waitForElementVisible(page.elements.modal);
    },
    'create new availability rule': browser => {
        const rulePageObject = rulePage(browser);

        browser
            .fillField('input[name=sw-field--rule-name]', 'Rule for new customers')
            .fillField('input[name=sw-field--rule-priority]', '1');

        rulePageObject.createBasicSelectCondition({
            type: 'Is new customer',
            ruleSelector: '.sw-condition-container__and-child--0',
            value: 'Yes',
            isMulti: false
        });

        browser
            .click(`${rulePageObject.elements.modal} ${rulePageObject.elements.primaryButton}`)
            .waitForElementNotPresent(rulePageObject.elements.modal)
            .checkNotification('The rule "Rule for new customers" has been saved successfully.')
            .expect.element('.sw-select-rule-create').to.have.text.that.contains('Rule for new customers');
    },
    'save and verify payment method': browser => {
        const page = paymentPage(browser);

        browser
            .click(page.elements.paymentSaveAction)
            .waitForElementVisible('.icon--small-default-checkmark-line-medium')
            .click(page.elements.smartBarBack)
            .waitForElementVisible('.sw-settings-payment-list')
            .fillGlobalSearchField(global.AdminFixtureService.basicFixture.name)
            .waitForElementNotPresent(page.elements.loader)
            .waitForElementPresent(page.elements.smartBarAmount);

        browser.expect.element(page.elements.smartBarAmount).to.have.text.that.contains('(1)');
        browser.expect.element(`${page.elements.gridRow}--0`).to.have.text.that.contains(global.AdminFixtureService.basicFixture.name);
    }
};
