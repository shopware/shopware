import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-category-tree-field': typeof Component;
    }
}
