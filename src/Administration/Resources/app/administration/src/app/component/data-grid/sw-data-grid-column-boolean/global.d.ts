import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-data-grid-column-boolean': typeof Component;
    }
}
