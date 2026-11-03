import type Component from './index';

/**
 * @sw-package checkout
 */

declare global {
    interface PublicComponents {
        'sw-bulk-edit-customer': typeof Component;
    }
}
