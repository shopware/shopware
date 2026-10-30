import SwSettingsShippingPriceMatrix from './index';

/**
 * @sw-package checkout
 */

Shopware.Component.register(SwSettingsShippingPriceMatrix);

declare global {
    interface PublicComponents {
        'sw-settings-shipping-price-matrix': typeof SwSettingsShippingPriceMatrix;
    }
}
