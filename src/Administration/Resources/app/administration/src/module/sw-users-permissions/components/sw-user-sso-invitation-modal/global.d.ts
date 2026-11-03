import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-user-sso-invitation-modal': typeof Component;
    }
}
