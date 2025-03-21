/**
 * @sw-package checkout
 */
import './in-app-purchase-checkout.store';

describe('src/app/store/in-app-purchase-checkout.store.ts', () => {
    let store = null;

    beforeEach(() => {
        store = Shopware.Store.get('inAppPurchaseCheckout');
    });

    it('should have initial state', () => {
        expect(store.entry).toBeNull();
        expect(store.extension).toBeNull();
    });

    it('should open the modal with the correct data', () => {
        const checkoutRequest = {
            featureId: 'TestFeature',
        };

        Shopware.Context.app.config.bundles = {
            TestExtension: {
                identifier: 'TestExtension',
            },
        };

        store.request(checkoutRequest, 'TestExtension');

        expect(store.entry).toEqual(checkoutRequest);
        expect(store.extension).toBe('TestExtension');
    });

    it('should throw an error if the extension is not found', () => {
        const checkoutRequest = {
            featureId: 'TestFeature',
        };
        const extensionName = 'TestExtension';

        Shopware.Context.app.config.bundles = {};

        expect(() => {
            store.request(checkoutRequest, extensionName);
        }).toThrow(new Error('Extension with the name "TestExtension" not found.'));
    });

    it('should close the modal', () => {
        store.dismiss();

        expect(store.entry).toBeNull();
        expect(store.extension).toBeNull();
    });
});
