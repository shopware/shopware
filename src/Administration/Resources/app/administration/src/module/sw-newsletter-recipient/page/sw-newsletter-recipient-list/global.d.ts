import type Component from './index';

/**
 * @sw-package after-sales
 */

declare global {
    interface PublicComponents {
        'sw-newsletter-recipient-list': typeof Component;
    }
}
