import type Component from './private';

/**
 * @sw-package discovery
 */

declare global {
    interface PrivateComponents {
        'sw-sales-channel-detail-analytics': typeof Component;
    }
}
