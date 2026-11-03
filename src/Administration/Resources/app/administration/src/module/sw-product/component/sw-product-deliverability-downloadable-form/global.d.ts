import type Component from './index';

/**
 * @sw-package inventory
 */

declare global {
    interface PublicComponents {
        'sw-product-deliverability-downloadable-form': typeof Component;
    }
}
