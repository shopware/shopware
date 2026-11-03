import type Component from './index';

/**
 * @sw-package after-sales
 */

declare global {
    interface PublicComponents {
        'sw-mail-template-index': typeof Component;
    }
}
