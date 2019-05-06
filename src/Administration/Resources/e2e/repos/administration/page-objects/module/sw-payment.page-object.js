const GeneralPageObject = require('../sw-general.page-object');

class PaymentPageObject extends GeneralPageObject {
    constructor(browser) {
        super(browser);

        this.elements = {
            ...this.elements,
            ...{
                paymentSaveAction: '.sw-payment-detail__save-action',
                shippingBackToListViewAction: '.sw-icon.icon--default-action-settings.sw-icon--small'
            }
        };
    }

    createPaymentMethod(name) {
        this.browser
            .fillField('#sw-field--paymentMethod-name', name)
            .fillField('#sw-field--paymentMethod-position', '10')
            .tickCheckbox('#sw-field--paymentMethod-active', true)
            .waitForElementNotPresent('.icon--small-default-checkmark-line-medium')
            .click(this.elements.paymentSaveAction)
            .waitForElementVisible('.icon--small-default-checkmark-line-medium');
    }
}

module.exports = browser => {
    return new PaymentPageObject(browser);
};
