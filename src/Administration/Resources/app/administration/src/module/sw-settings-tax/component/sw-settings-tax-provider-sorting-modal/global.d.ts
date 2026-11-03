import type Component from './index';

/**
 * @sw-package checkout
 */

declare global {
    interface PublicComponents {
        'sw-settings-tax-provider-sorting-modal': typeof Component;
    }
}
