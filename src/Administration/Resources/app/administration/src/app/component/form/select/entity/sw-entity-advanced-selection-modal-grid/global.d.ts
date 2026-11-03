import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-entity-advanced-selection-modal-grid': typeof Component;
    }
}
