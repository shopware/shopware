import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-advanced-selection-product': typeof Component;
    }
}
