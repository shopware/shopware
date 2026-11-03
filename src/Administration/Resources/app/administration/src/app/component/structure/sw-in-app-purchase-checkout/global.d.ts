import type Component from './private';

/**
 * @sw-package checkout
 */

declare global {
    interface PrivateComponents {
        'sw-in-app-purchase-checkout': typeof Component;
    }
}
