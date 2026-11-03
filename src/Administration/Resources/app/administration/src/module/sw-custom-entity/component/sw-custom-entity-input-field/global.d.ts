import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-custom-entity-input-field': typeof Component;
    }
}
