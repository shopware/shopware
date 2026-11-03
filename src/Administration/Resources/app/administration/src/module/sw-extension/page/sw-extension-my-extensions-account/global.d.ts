import type Component from './private';

/**
 * @sw-package checkout
 */

declare global {
    interface PrivateComponents {
        'sw-extension-my-extensions-account': typeof Component;
    }
}
