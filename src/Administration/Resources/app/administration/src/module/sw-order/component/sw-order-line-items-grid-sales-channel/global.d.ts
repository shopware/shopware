import type Component from './index';

/**
 * @sw-package checkout
 */

declare global {
    interface PublicComponents {
        'sw-order-line-items-grid-sales-channel': typeof Component;
    }
}
