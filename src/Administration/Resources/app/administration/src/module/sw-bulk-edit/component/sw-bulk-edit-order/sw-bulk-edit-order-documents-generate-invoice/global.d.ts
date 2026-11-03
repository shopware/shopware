import type Component from './index';

/**
 * @sw-package checkout
 */

declare global {
    interface PublicComponents {
        'sw-bulk-edit-order-documents-generate-invoice': typeof Component;
    }
}
