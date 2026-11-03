import type Component from './index';

/**
 * @sw-package checkout
 */

declare global {
    interface PublicComponents {
        'sw-order-state-change-modal': typeof Component;
    }
}
