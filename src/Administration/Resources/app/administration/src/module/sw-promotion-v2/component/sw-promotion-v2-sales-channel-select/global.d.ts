import type Component from './index';

/**
 * @sw-package checkout
 */

declare global {
    interface PublicComponents {
        'sw-promotion-v2-sales-channel-select': typeof Component;
    }
}
