import type Component from './private';

/**
 * @sw-package checkout
 */

declare global {
    interface PrivateComponents {
        'sw-extension-app-module-error-page': typeof Component;
    }
}
