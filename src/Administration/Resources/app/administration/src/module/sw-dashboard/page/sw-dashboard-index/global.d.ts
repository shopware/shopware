import type Component from './private';

/**
 * @sw-package after-sales
 */

declare global {
    interface PrivateComponents {
        'sw-dashboard-index': typeof Component;
    }
}
