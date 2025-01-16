/**
 * @package checkout
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
        const extension = {
            name: 'TestExtension',
            baseUrl: 'http://example.com',
            permissions: [],
            type: 'app',
        };

        store.request(checkoutRequest, extension);

        expect(store.entry).toEqual(checkoutRequest);
        expect(store.extension).toEqual(extension);
    });

    it('should open the modal with the correct data when extension is a string', () => {
        const checkoutRequest = {
            featureId: 'TestFeature',
        };
        const extensionName = 'TestExtension';
        const extension = {
            name: extensionName,
            baseUrl: 'http://example.com',
            permissions: [],
            version: '1.0.0',
            type: 'plugin',
            integrationId: '123',
            active: true,
        };

        Shopware.State._store.state.extensions = {};
        Shopware.State.commit('extensions/addExtension', extension);

        store.request(checkoutRequest, extensionName);

        expect(store.entry).toEqual(checkoutRequest);
        expect(store.extension).toEqual(extension);
    });

    it('should throw an error if the extension is not found', () => {
        const checkoutRequest = {
            featureId: 'TestFeature',
        };
        const extensionName = 'TestExtension';

        Shopware.State._store.state.extensions = {};

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
