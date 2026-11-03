import type Component from './index';

/**
 * @sw-package discovery
 */

declare global {
    interface PublicComponents {
        'sw-sales-channel-defaults-select': typeof Component;
    }
}
