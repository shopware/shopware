import type Component from './index';

/**
 * @sw-package discovery
 */

declare global {
    interface PublicComponents {
        'sw-sales-channel-products-assignment-modal': typeof Component;
    }
}
