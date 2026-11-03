import type Component from './private';

/**
 * @sw-package fundamentals@after-sales
 */

declare global {
    interface PrivateComponents {
        'sw-import-export-edit-profile-modal': typeof Component;
    }
}
