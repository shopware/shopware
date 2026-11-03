import type Component from './index';

/**
 * @sw-package fundamentals@discovery
 */

declare global {
    interface PublicComponents {
        'sw-settings-country-state': typeof Component;
    }
}
