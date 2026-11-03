import type Component from './index';

/**
 * @sw-package checkout
 */

declare global {
    interface PublicComponents {
        'sw-customer-imitate-customer-modal': typeof Component;
    }
}
