import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-extension-sdk-module': typeof Component;
    }
}
