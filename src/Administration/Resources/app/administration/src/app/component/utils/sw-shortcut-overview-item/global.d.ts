import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-shortcut-overview-item': typeof Component;
    }
}
