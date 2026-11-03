import type Component from './private';

/**
 * @sw-package fundamentals@after-sales
 */

declare global {
    interface PrivateComponents {
        'sw-settings-rule-add-assignment-listing': typeof Component;
    }
}
