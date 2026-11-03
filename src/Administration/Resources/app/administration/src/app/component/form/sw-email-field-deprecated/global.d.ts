import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-email-field-deprecated': typeof Component;
    }
}
