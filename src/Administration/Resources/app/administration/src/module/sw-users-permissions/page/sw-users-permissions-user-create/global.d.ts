import type Component from './index';

/**
 * @sw-package fundamentals@framework
 */

declare global {
    interface PublicComponents {
        'sw-users-permissions-user-create': typeof Component;
    }
}
