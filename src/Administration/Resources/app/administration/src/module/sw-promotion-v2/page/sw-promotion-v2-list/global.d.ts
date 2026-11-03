import type Component from './index';

/**
 * @sw-package checkout
 */

declare global {
    interface PublicComponents {
        'sw-promotion-v2-list': typeof Component;
    }
}
