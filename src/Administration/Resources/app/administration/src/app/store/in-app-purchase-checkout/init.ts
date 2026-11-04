/**
 * @sw-package checkout
 */
import { useInAppPurchaseCheckoutStore } from './src/in-app-purchase-checkout.store';
import { useExtensionsStore } from './dependencies';

/**
 * @private
 */
export default function initializeInAppPurchaseCheckout(): void {
    Shopware.ExtensionAPI.handle('iapCheckout', (entry, { _event_ }) => {
        const extension = Object.values(useExtensionsStore().extensionsState).find((ext) =>
            ext.baseUrl.startsWith(_event_.origin),
        );

        if (!extension) {
            throw new Error(`Extension with the origin "${_event_.origin}" not found.`);
        }

        useInAppPurchaseCheckoutStore().request(entry, extension.name);
    });
}
