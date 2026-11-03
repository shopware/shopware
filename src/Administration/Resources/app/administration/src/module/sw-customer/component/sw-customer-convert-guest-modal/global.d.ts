import type Component from './index';

/**
 * @sw-package checkout
 */

declare global {
    interface PublicComponents {
        'sw-customer-convert-guest-modal': typeof Component;
    }
}
