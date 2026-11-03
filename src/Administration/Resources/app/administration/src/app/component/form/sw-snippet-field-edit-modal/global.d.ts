import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-snippet-field-edit-modal': typeof Component;
    }
}
