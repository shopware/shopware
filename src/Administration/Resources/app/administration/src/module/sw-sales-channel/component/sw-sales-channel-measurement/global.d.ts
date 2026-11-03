import type Component from './index';

/**
 * @sw-package inventory
 */

declare global {
    interface PublicComponents {
        'sw-sales-channel-measurement': typeof Component;
    }
}
