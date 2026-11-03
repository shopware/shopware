import type Component from './index';

/**
 * @sw-package checkout
 */

declare global {
    interface PublicComponents {
        'sw-order-nested-line-items-modal': typeof Component;
    }
}
