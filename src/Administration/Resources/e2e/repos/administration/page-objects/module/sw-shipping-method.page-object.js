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
            .tickCheckbox('input[name=sw-field--shippingMethod-bindShippingfree]', false);

        this.browser
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
