import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-data-grid-inline-edit': typeof Component;
    }
}
