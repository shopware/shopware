import type Component from './index';

/**
 * @sw-package fundamentals@after-sales
 */

declare global {
    interface PublicComponents {
        'sw-condition-line-item-goods-total': typeof Component;
    }
}
