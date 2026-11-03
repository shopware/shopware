import type Component from './private';

/**
 * @sw-package checkout
 */

declare global {
    interface PrivateComponents {
        'sw-extension-privacy-policy-extensions-modal': typeof Component;
    }
}
