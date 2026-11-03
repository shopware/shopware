import type Component from './index';

/**
 * @sw-package inventory
 */

declare global {
    interface PublicComponents {
        'sw-product-variants-configurator-restrictions': typeof Component;
    }
}
