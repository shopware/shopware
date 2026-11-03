import type Component from './index';

/**
 * @sw-package discovery
 */

declare global {
    interface PublicComponents {
        'sw-settings-customer-group-list': typeof Component;
    }
}
