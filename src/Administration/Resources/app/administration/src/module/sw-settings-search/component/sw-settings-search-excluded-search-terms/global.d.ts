import type Component from './index';

/**
 * @sw-package inventory
 */

declare global {
    interface PublicComponents {
        'sw-settings-search-excluded-search-terms': typeof Component;
    }
}
