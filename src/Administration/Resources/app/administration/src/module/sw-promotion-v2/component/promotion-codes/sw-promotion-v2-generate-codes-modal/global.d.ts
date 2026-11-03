import type Component from './index';

/**
 * @sw-package checkout
 */

declare global {
    interface PublicComponents {
        'sw-promotion-v2-generate-codes-modal': typeof Component;
    }
}
