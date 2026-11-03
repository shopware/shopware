import type Component from './private';

/**
 * @sw-package checkout
 */

declare global {
    interface PrivateComponents {
        'sw-order-nested-line-items-row': typeof Component;
    }
}
