import type Component from './index';

/**
 * @sw-package checkout
 */

declare global {
    interface PublicComponents {
        'sw-bulk-edit-save-modal-success': typeof Component;
    }
}
