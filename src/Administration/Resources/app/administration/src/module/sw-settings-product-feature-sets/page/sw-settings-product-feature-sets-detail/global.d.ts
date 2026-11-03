import type Component from './index';

/**
 * @sw-package inventory
 */

declare global {
    interface PublicComponents {
        'sw-settings-product-feature-sets-detail': typeof Component;
    }
}
