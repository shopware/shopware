import type Component from './index';

/**
 * @sw-package fundamentals@framework
 */

declare global {
    interface PublicComponents {
        'sw-settings-login-registration': typeof Component;
    }
}
