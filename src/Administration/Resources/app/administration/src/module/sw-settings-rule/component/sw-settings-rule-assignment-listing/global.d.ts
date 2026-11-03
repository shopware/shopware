import type Component from './private';

/**
 * @sw-package fundamentals@after-sales
 */

declare global {
    interface PrivateComponents {
        'sw-settings-rule-assignment-listing': typeof Component;
    }
}
