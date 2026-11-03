import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-settings-usage-data-profile-consent': typeof Component;
    }
}
