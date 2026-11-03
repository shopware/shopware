import type Component from './index';

/**
 * @sw-package fundamentals@framework
 */

declare global {
    interface PublicComponents {
        'sw-users-permissions-user-detail': typeof Component;
    }
}
