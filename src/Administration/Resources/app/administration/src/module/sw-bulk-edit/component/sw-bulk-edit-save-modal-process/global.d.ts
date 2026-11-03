import type Component from './index';

/**
 * @sw-package framework
 */

declare global {
    interface PublicComponents {
        'sw-bulk-edit-save-modal-process': typeof Component;
    }
}
