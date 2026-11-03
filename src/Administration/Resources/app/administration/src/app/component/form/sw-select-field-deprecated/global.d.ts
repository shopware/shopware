import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-select-field-deprecated': typeof Component;
    }
}
