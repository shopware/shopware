import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-data-grid-skeleton': typeof Component;
    }
}
