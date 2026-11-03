import type Component from './private';

/**
 * @sw-package data-services
 */

declare global {
    interface PrivateComponents {
        'sw-settings-usage-data-general': typeof Component;
    }
}
