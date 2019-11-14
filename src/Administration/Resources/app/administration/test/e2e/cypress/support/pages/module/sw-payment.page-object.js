const GeneralPageObject = require('../sw-general.page-object');

export default class PaymentPageObject extends GeneralPageObject {
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
}
