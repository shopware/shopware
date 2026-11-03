import type Component from './index';

/**
 * @sw-package inventory
 */

declare global {
    interface PublicComponents {
        'sw-settings-listing-visibility-detail': typeof Component;
    }
}
