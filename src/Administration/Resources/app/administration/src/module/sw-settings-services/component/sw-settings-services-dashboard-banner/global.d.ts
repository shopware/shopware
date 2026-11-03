import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-settings-services-dashboard-banner': typeof Component;
    }
}
