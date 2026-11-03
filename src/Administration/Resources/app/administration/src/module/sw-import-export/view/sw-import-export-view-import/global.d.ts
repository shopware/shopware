import type Component from './private';

/**
 * @sw-package fundamentals@after-sales
 */

declare global {
    interface PrivateComponents {
        'sw-import-export-view-import': typeof Component;
    }
}
