import type Component from './index';

/**
 * @sw-package checkout
 */

declare global {
    interface PublicComponents {
        'sw-settings-state-machine-state-detail': typeof Component;
    }
}
