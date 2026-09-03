/**
 * @sw-package framework
 */
import { isAirGapped, isShopwareStoreUnavailable } from './air-gapped.helper';

describe('core/helper/air-gapped.helper.ts', () => {
    beforeEach(() => {
        Shopware.Store.get('context').app.config.settings = {
            disableExtensionManagement: false,
            airGapped: false,
        };
    });

    it('treats missing settings as connected', () => {
        Shopware.Store.get('context').app.config.settings = undefined;

        expect(isAirGapped()).toBe(false);
        expect(isShopwareStoreUnavailable()).toBe(false);
    });

    it('detects air-gapped mode independently of extension management', () => {
        Shopware.Store.get('context').app.config.settings.airGapped = true;

        expect(isAirGapped()).toBe(true);
        expect(isShopwareStoreUnavailable()).toBe(true);
    });

    it('treats disabled extension management as store-unavailable without being air-gapped', () => {
        Shopware.Store.get('context').app.config.settings.disableExtensionManagement = true;

        expect(isAirGapped()).toBe(false);
        expect(isShopwareStoreUnavailable()).toBe(true);
    });

    it('keeps a connected shop store-available', () => {
        expect(isAirGapped()).toBe(false);
        expect(isShopwareStoreUnavailable()).toBe(false);
    });
});
