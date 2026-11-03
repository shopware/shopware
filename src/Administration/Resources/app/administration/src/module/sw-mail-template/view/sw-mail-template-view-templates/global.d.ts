import type Component from './private';

/**
 * @sw-package after-sales
 */

declare global {
    interface PrivateComponents {
        'sw-mail-template-view-templates': typeof Component;
    }
}
