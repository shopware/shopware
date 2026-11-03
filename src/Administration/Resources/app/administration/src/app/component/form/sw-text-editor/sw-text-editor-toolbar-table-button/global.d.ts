import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-text-editor-toolbar-table-button': typeof Component;
    }
}
