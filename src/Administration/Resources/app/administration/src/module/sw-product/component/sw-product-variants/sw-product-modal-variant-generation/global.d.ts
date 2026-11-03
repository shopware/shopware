import type Component from './index';

/**
 * @sw-package inventory
 */

declare global {
    interface PublicComponents {
        'sw-product-modal-variant-generation': typeof Component;
    }
}
