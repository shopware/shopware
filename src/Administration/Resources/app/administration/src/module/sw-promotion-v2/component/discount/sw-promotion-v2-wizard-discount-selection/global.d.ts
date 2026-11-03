import type Component from './private';

/**
 * @sw-package checkout
 */

declare global {
    interface PrivateComponents {
        'sw-promotion-v2-wizard-discount-selection': typeof Component;
    }
}
