import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-text-field-deprecated': typeof Component;
    }
}
