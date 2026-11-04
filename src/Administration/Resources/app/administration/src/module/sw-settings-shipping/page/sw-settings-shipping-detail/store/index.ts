/**
 * @sw-package checkout
 *
 * The state of the shipping method detail page: the shipping method and its price matrices, shared by the page and the matrix components.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/swShippingDetail`.
 */
/**
 * @public
 * @export-namespace stores
 */
export { swShippingDetailStoreId, useSwShippingDetailStore } from './src/sw-shipping-detail.store';

/** @public */
export type { SwShippingDetailStore } from './src/sw-shipping-detail.store';
