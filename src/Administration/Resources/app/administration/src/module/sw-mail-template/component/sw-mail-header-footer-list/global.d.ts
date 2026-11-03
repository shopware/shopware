import type Component from './index';

/**
 * @sw-package after-sales
 */

declare global {
    interface PublicComponents {
        'sw-mail-header-footer-list': typeof Component;
    }
}
