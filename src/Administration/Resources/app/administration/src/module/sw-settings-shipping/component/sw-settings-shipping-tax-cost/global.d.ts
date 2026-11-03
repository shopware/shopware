import type Component from './index';

/**
 * @sw-package checkout
 */

declare global {
    interface PublicComponents {
        'sw-settings-shipping-tax-cost': typeof Component;
    }
}
