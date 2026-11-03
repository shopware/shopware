import type Component from './index';

/**
 * @sw-package inventory
 */

declare global {
    interface PublicComponents {
        'sw-manufacturer-detail': typeof Component;
    }
}
