const shippingMethodPage = require('administration/page-objects/module/sw-shipping-method.page-object.js');
const rulePage = require('administration/page-objects/module/sw-rule.page-object.js');

module.exports = {
    '@tags': ['settings', 'shipping', 'edit', 'shipping-availability-rule', 'rule'],
    before: (browser, done) => {
        return global.ShippingFixtureService.setShippingFixture().then(() => {
            done();
        });
    },
    'find shipping method to be edited': browser => {
        const page = shippingMethodPage(browser);

        browser
            .openMainMenuEntry({
                targetPath: '#/sw/settings/index',
                mainMenuId: 'sw-settings'
            })
            .click('#sw-settings-shipping')
            .assert.urlContains('#/sw/settings/shipping/index')
            .expect.element(`${page.elements.dataGridRow}--1`).to.have.text.that.contains('Nachnahme');

        browser
            .clickContextMenuItem(page.elements.contextMenuButton, {
                menuActionSelector: '.sw-settings-shipping-list__edit-action',
                scope: `${page.elements.dataGridRow}--1`
            })
            .expect.element(page.elements.smartBarHeader).to.have.text.that.contains('Nachnahme');
    },
    'add price rule to fulfill save requirements': browser => {
        const page = shippingMethodPage(browser);

        browser
            .getLocationInView('.sw-settings-shipping-price-matrices__actions')
            .fillSwSelectComponent('.sw-settings-shipping-price-matrix__top-container .sw-select-rule-create', {
                value: 'Cart >= 0',
                searchTerm: 'Cart >= 0'
            });

        page.createShippingMethodPriceRule();
    },
    'open modal for new availability rule': browser => {
        const page = shippingMethodPage(browser);

        browser
            .waitForElementVisible('.sw-settings-shipping-detail__condition_container .sw-select-rule-create')
            .click('.sw-settings-shipping-detail__condition_container .sw-select-rule-create')
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
    'save and verify shipping method': browser => {
        const page = shippingMethodPage(browser);

        browser
            .click(page.elements.shippingSaveAction)
            .waitForElementVisible('.icon--small-default-checkmark-line-medium')
            .click(page.elements.smartBarBack)
            .waitForElementVisible('.sw-settings-shipping-list__content')
            .fillGlobalSearchField('Nachnahme')
            .waitForElementNotPresent(page.elements.loader)
            .waitForElementPresent(page.elements.smartBarAmount);

        browser.expect.element(page.elements.smartBarAmount).to.have.text.that.contains('(1)');
        browser.expect.element(`${page.elements.dataGridRow}--0`).to.have.text.that.contains('Nachnahme');
    }
};
