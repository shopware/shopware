import type Component from './index';

/**
 * @sw-package after-sales
 */

declare global {
    interface PublicComponents {
        'sw-order-document-settings-modal': typeof Component;
    }
}
