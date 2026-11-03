import type SwOrderList from './index';

/**
 * @sw-package checkout
 */

declare global {
    interface PublicComponents {
        'sw-order-list': typeof SwOrderList;
    }
}
