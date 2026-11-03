import type Component from './index';

/**
 * @sw-package fundamentals@after-sales
 */

declare global {
    interface PublicComponents {
        'sw-import-export-new-profile-wizard': typeof Component;
    }
}
