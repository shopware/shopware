import type SwOrderDetail from './index';

/**
 * @sw-package checkout
 */

declare global {
    interface PublicComponents {
        'sw-order-detail': typeof SwOrderDetail;
    }
}
