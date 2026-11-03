import type Component from './private';

/**
 * @sw-package discovery
 */

declare global {
    interface PrivateComponents {
        'sw-category-sales-channel-multi-select': typeof Component;
    }
}
