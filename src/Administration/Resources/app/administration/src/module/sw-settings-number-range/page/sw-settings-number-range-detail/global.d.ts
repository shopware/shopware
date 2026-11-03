import type Component from './index';

/**
 * @sw-package inventory
 */

declare global {
    interface PublicComponents {
        'sw-settings-number-range-detail': typeof Component;
    }
}
