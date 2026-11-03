import type Component from './index';

/**
 * @sw-package discovery
 */

declare global {
    interface PublicComponents {
        'sw-settings-delivery-time-detail': typeof Component;
    }
}
