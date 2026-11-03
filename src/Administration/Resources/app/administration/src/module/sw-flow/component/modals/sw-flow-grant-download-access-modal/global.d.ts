import type Component from './private';

/**
 * @sw-package after-sales
 */

declare global {
    interface PrivateComponents {
        'sw-flow-grant-download-access-modal': typeof Component;
    }
}
