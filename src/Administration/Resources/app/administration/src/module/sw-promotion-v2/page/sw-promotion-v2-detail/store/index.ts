/**
 * @sw-package checkout
 *
 * The state of the promotion detail page: the promotion and the changes to its customers and set groups that the page saves with it.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/swPromotionDetail`.
 */
/**
 * @public
 * @export-namespace stores
 */
export { swPromotionDetailStoreId, useSwPromotionDetailStore } from './src/sw-promotion-detail.store';

/** @public */
export type { SwPromotionDetailStore } from './src/sw-promotion-detail.store';
