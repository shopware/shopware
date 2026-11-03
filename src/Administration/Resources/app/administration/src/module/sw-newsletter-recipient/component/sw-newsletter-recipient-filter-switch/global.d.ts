import type Component from './index';

/**
 * @sw-package after-sales
 */

declare global {
    interface PublicComponents {
        'sw-newsletter-recipient-filter-switch': typeof Component;
    }
}
