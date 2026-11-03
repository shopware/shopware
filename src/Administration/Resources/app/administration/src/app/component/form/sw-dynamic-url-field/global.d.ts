import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-dynamic-url-field': typeof Component;
    }
}
