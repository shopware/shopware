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
            .tickCheckbox('input[name=sw-field--shippingMethod-active]', true)
            .tickCheckbox('input[name=sw-field--shippingMethod-bindShippingfree]', true)
            .tickCheckbox('input[name=sw-field--shippingMethod-bindShippingfree]', false)
            .fillSwSelectComponent('.sw-settings-shipping-detail-base__delivery-time', {
                value: '1-3 days',
                searchTerm: '1-3'
            });

        this.browser
            .click(this.elements.shippingSaveAction)
            .checkNotification(`Shipping rate "${name}" has been saved successfully.`);
    }

    createShippingMethodPriceRule(name) {
        this.browser
            .click('.sw-shipping-detail-page__price-settings')
            .waitForElementVisible('.context-prices__actions button')
            .click('.context-prices__actions button')
            .click('.context-prices__rule')
            .waitForElementVisible('.sw-select__results-list')
            .click('.sw-select-option--0')
            .expect.element('.sw-card__title').to.have.text.that.contains('Ruler');

        this.browser
            .waitForElementVisible('.context-prices__prices')
            .fillField(`${this.elements.gridRow}--0 input[name=sw-field--item-quantityEnd]`, '20')
            .fillField(`${this.elements.gridRow}--0 input[name=sw-field--item-price]`, '10')
            .fillField(`${this.elements.gridRow}--1 input[name=sw-field--item-price]`, '8')
            .click(this.elements.shippingSaveAction)
            .checkNotification(`Shipping rate "${name}" has been saved successfully.`);
    }

    moveToListViewFromDetail() {
        this.browser
            .click(this.elements.shippingBackToListViewAction)
            .waitForElementVisible('.sw-grid-column.sw-grid-column--left');
    }
}

module.exports = browser => {
    return new ShippingMethodPageObject(browser);
};
