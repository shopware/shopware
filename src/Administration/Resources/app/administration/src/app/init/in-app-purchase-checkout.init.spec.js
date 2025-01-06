/**
 * @package checkout
 */
import { purchase } from '@shopware-ag/meteor-admin-sdk/es/iap';
import initializeInAppPurchaseCheckout from './in-app-purchase-checkout.init';
import 'src/app/store/in-app-purchase-checkout.store';

describe('src/app/init/in-app-purchase.init.ts', () => {
    beforeAll(() => {
        initializeInAppPurchaseCheckout();
    });

    beforeEach(() => {
        Shopware.State._store.state.extensions = {};
        Shopware.State.commit('extensions/addExtension', {
            name: 'jestapp',
            baseUrl: '',
            permissions: [],
            version: '1.0.0',
            type: 'app',
            integrationId: '123',
            active: true,
        });

        Shopware.Store.get('inAppPurchaseCheckout').$reset();
    });

    it('should handle incoming inAppPurchases requests', async () => {
        await purchase({
            title: 'Your purchase title',
            variant: 'default',
            showHeader: true,
            showFooter: true,
            closable: true,
        });

        expect(Shopware.Store.get('inAppPurchaseCheckout').entry).toBeDefined();
    });
});
