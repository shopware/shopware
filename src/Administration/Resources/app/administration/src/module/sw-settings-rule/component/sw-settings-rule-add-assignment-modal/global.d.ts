import type Component from './index';

/**
 * @sw-package fundamentals@after-sales
 */

declare global {
    interface PublicComponents {
        'sw-settings-rule-add-assignment-modal': typeof Component;
    }
}
