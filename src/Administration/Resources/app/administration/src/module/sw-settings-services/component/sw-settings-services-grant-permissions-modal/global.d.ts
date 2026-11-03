import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-settings-services-grant-permissions-modal': typeof Component;
    }
}
