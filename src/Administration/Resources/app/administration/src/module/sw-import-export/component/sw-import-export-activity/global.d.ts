import type Component from './index';

/**
 * @sw-package fundamentals@after-sales
 */

declare global {
    interface PublicComponents {
        'sw-import-export-activity': typeof Component;
    }
}
