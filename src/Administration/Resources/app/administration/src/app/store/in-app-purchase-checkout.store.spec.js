import './in-app-purchase-checkout.store';

describe('src/app/store/in-app-purchases.store.ts', () => {
    let store = null;
    beforeEach(() => {
        store = Shopware.Store.get('inAppPurchaseCheckout');
    });

    it('should open the modal with the correct data', () => {
        const checkoutRequest = {
            featureId: 'Test Feature',
        };

        store.request(checkoutRequest);

        expect(store.entry).toEqual(checkoutRequest);
    });

    it('should close the modal', () => {
        store.dismiss();

        expect(store.entry).toBeNull();
    });
});
