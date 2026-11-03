import type Component from './private';

/**
 * @sw-package inventory
 */

declare global {
    interface PrivateComponents {
        'sw-category-detail-custom-entity': typeof Component;
    }
}
