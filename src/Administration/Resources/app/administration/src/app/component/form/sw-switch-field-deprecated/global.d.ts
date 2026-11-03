import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-switch-field-deprecated': typeof Component;
    }
}
