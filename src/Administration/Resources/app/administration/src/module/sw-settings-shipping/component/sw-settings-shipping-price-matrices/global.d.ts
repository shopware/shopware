import type Component from './index';

/**
 * @sw-package checkout
 */

declare global {
    interface PublicComponents {
        'sw-settings-shipping-price-matrices': typeof Component;
    }
}
