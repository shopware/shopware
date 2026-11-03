import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-bulk-edit-custom-fields': typeof Component;
    }
}
