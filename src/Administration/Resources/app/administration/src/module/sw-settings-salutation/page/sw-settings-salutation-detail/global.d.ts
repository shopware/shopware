import type Component from './index';

/**
 * @sw-package checkout
 */

declare global {
    interface PublicComponents {
        'sw-settings-salutation-detail': typeof Component;
    }
}
