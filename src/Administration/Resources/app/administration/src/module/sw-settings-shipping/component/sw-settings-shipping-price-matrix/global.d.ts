import type SwSettingsShippingPriceMatrix from './index';

/**
 * @sw-package checkout
 */

declare global {
    interface PublicComponents {
        'sw-settings-shipping-price-matrix': typeof SwSettingsShippingPriceMatrix;
    }
}
