import type Component from './index';

/**
 * @sw-package inventory
 */

declare global {
    interface PublicComponents {
        'sw-bulk-edit-product-media-form': typeof Component;
    }
}
