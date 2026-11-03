import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-sso-users-permission-user-detail': typeof Component;
    }
}
