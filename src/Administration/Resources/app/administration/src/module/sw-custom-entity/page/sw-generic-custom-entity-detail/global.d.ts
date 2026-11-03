import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-generic-custom-entity-detail': typeof Component;
    }
}
