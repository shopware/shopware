import type Component from './private';

/**
 * @sw-package checkout
 */

declare global {
    interface PrivateComponents {
        'sw-order-promotion-tag-field': typeof Component;
    }
}
