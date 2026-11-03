import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-entity-many-to-many-select': typeof Component;
    }
}
