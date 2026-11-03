import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-app-topbar-sidebar': typeof Component;
    }
}
