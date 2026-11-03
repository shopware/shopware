import type Component from './private';

/**
 * @sw-package inventory
 */

declare global {
    interface PrivateComponents {
        'sw-product-visibility-select': typeof Component;
    }
}
