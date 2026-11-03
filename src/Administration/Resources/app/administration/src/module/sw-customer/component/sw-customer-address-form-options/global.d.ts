import type Component from './index';

/**
 * @sw-package checkout
 */

declare global {
    interface PublicComponents {
        'sw-customer-address-form-options': typeof Component;
    }
}
