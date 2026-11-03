import type Component from './private';

/**
 * @sw-package framework
 */

declare global {
    interface PrivateComponents {
        'sw-user-sso-access-key-create-modal': typeof Component;
    }
}
