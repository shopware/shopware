import type Component from './private';

/**
 * @sw-package checkout
 */

declare global {
    interface PrivateComponents {
        'sw-settings-tax-provider-sorting-modal': typeof Component;
    }
}
