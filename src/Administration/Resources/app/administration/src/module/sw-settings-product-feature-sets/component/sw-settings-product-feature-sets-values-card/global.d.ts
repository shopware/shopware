import type Component from './index';

/**
 * @sw-package inventory
 */

declare global {
    interface PublicComponents {
        'sw-settings-product-feature-sets-values-card': typeof Component;
    }
}
