import type Component from './private';

/**
 * @sw-package after-sales
 */

declare global {
    interface PrivateComponents {
        'sw-flow-set-entity-custom-field-modal': typeof Component;
    }
}
