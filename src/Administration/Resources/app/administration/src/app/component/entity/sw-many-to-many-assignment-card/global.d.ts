import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-many-to-many-assignment-card': typeof Component;
    }
}
