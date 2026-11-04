/**
 * @sw-package inventory
 *
 * The state of the product detail page: the product, its parent and what the views load around it, shared by the page, its views and the product bulk edit.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/swProductDetail`.
 */
/**
 * @public
 * @shopware-kind stores
 */
export { swProductDetailStoreId, useSwProductDetailStore } from './src/sw-product-detail.store';

/** @public */
export type { SwProductDetailStore } from './src/sw-product-detail.store';
