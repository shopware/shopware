import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-app-wrong-app-url-modal': typeof Component;
    }
}
