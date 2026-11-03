import type Component from './index';

/**
 * @sw-package discovery
 */

declare global {
    interface PublicComponents {
        'sw-settings-snippet-filter-switch': typeof Component;
    }
}
