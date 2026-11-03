import type Component from './private';

/**
 * @sw-package inventory
 */

declare global {
    interface PrivateComponents {
        'sw-product-stream-grid-preview': typeof Component;
    }
}
