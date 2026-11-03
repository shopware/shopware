import type Component from './private';

/**
 * @sw-package after-sales
 */

declare global {
    interface PrivateComponents {
        'sw-mail-template-preview-modal': typeof Component;
    }
}
