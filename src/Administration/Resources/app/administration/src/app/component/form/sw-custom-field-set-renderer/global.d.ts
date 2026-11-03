import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-custom-field-set-renderer': typeof Component;
    }
}
