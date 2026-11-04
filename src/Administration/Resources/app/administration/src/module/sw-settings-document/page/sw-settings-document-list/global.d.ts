import type Component from './index';

/**
 * @sw-package after-sales
 */

declare global {
    interface PublicComponents {
        'sw-settings-document-list': typeof Component;
    }
}
