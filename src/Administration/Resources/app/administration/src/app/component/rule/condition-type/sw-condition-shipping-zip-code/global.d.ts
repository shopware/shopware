import type Component from './index';

/**
 * @sw-package fundamentals@after-sales
 */

declare global {
    interface PublicComponents {
        'sw-condition-shipping-zip-code': typeof Component;
    }
}
