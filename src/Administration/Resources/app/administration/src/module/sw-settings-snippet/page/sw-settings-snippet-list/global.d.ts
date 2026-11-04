import type Component from './index';

/**
 * @sw-package discovery
 */

declare global {
    interface PublicComponents {
        'sw-settings-snippet-list': typeof Component;
    }
}
