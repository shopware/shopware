import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-sidebar-navigation-item': typeof Component;
    }
}
