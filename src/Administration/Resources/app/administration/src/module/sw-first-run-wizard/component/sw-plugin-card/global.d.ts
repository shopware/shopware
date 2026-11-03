import type Component from './private';

/**
 * @sw-package fundamentals@after-sales
 */

declare global {
    interface PrivateComponents {
        'sw-plugin-card': typeof Component;
    }
}
