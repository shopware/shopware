import type Component from './private';

/**
 * @sw-package checkout
 */

declare global {
    interface PrivateComponents {
        'sw-extension-deactivation-modal': typeof Component;
    }
}
