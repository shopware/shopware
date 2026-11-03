import type Component from './index';

/**
 * @sw-package checkout
 */

declare global {
    interface PublicComponents {
        'sw-bulk-edit-order-documents-download-documents': typeof Component;
    }
}
