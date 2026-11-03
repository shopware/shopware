import type Component from './index';

/**
 * @sw-package checkout
 */

declare global {
    interface PublicComponents {
        'sw-settings-payment-sorting-modal': typeof Component;
    }
}
