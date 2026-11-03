import type Component from './private';

/**
 * @sw-package after-sales
 */

declare global {
    interface PrivateComponents {
        'sw-flow-event-change-confirm-modal': typeof Component;
    }
}
