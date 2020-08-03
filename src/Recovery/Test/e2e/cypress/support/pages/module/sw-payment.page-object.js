import elements from '../sw-general.page-object';

export default class PaymentPageObject {
    constructor() {
        this.elements = {
            ...elements,
            ...{
                paymentSaveAction: '.sw-payment-detail__save-action',
                shippingBackToListViewAction: '.sw-icon.icon--default-action-settings.sw-icon--small'
            }
        };
    }
}
