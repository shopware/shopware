const GeneralPageObject = require('../sw-general.page-object');

class ShippingMethodPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);

        this.elements = {
            ...this.elements,
            ...{
                shippingSaveAction: '.sw-settings-shipping-method-detail__save-action',
                shippingBackToListViewAction: '.sw-icon.icon--default-action-settings.sw-icon--small'
            }
        };
    }

    createShippingMethod(name) {
        this.browser
            .fillField('input[name=sw-field--shippingMethod-name]', name)
            .tickCheckbox('input[name=sw-field--shippingMethod-active]', false)
            .tickCheckbox('input[name=sw-field--shippingMethod-active]', true)
            .fillSwSelectComponent('.sw-settings-shipping-detail__delivery-time', {
                value: '1-3 days',
                searchTerm: '1-3'
            })
            .fillSwSelectComponent('.sw-settings-shipping-detail__top-rule', {
                value: 'Cart >= 0',
                searchTerm: 'Cart >= 0'
            });

        this.createShippingMethodPriceRule();

        this.browser
            .waitForElementNotPresent('.icon--small-default-checkmark-line-medium')
            .click(this.elements.shippingSaveAction)
            .waitForElementVisible('.icon--small-default-checkmark-line-medium');
    }

    createShippingMethodPriceRule() {
        this.selectPriceCalculation('.sw-settings-shipping-price-matrix__empty .sw-select', { optionSelector: '.sw-select-option--0', value: 'Line item count' });

        this.browser
            .waitForElementNotPresent('.sw-settings-shipping-price-matrix__empty .sw-select')
            .moveToElement(`${this.elements.dataGridRow}--0 .sw-data-grid__cell--quantityStart`, 5, 5)
            .doubleClick()
            .waitForElementPresent('.is--inline-edit')
            .fillField(`${this.elements.dataGridRow}--0 .sw-data-grid__cell--quantityStart input`, '0', true)
            .fillField(`${this.elements.dataGridRow}--0 .sw-data-grid__cell--quantityEnd input`, '10', true)
            .fillField(`${this.elements.dataGridRow}--0 .sw-data-grid__cell--price input`, '12.5', true)
            .click('.sw-data-grid__inline-edit-save')
            .waitForElementVisible(`${this.elements.dataGridRow}--1`)
            .moveToElement(`${this.elements.dataGridRow}--1 .sw-data-grid__cell--quantityStart`, 5, 5)
            .doubleClick()
            .waitForElementPresent('.is--inline-edit')
            .fillField(`${this.elements.dataGridRow}--1 .sw-data-grid__cell--price input`, '7.42', false)
            .click('.sw-data-grid__inline-edit-save')
            .waitForElementNotPresent('.sw-data-grid__inline-edit-save')
            .waitForElementNotPresent('.icon--small-default-checkmark-line-medium')
            .click(this.elements.shippingSaveAction)
            .waitForElementVisible('.icon--small-default-checkmark-line-medium');
    }

    selectPriceCalculation(selector, { optionSelector, value }) {
        this.browser
            .waitForElementVisible(selector)
            .click(selector)
            .waitForElementVisible(optionSelector)
            .assert.containsText(optionSelector, value)
            .click(optionSelector);
    }

    moveToListViewFromDetail() {
        this.browser
            .click(this.elements.shippingBackToListViewAction)
            .waitForElementVisible('.sw-settings-shipping-list__content');
    }
}

module.exports = browser => {
    return new ShippingMethodPageObject(browser);
};
