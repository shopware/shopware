import type Component from './index';

/**
 * @sw-package checkout
 */

declare global {
    interface PublicComponents {
        'sw-promotion-v2-settings-trigger': typeof Component;
    }
}
