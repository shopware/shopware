import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-user-sso-status-label': typeof Component;
    }
}
