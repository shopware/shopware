import type Component from './index';

/**
 * @sw-package checkout
 */

declare global {
    interface PublicComponents {
        'sw-order-create-invalid-promotion-modal': typeof Component;
    }
}
