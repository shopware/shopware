/**
 * @sw-package inventory
 *
 * The state of the category and landing page detail pages: the active category or landing page, shared by the page, its views and the trees.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/swCategoryDetail`.
 */
/**
 * @public
 * @shopware-kind stores
 */
export { swCategoryDetailStoreId, useSwCategoryDetailStore } from './src/sw-category-detail.store';

/** @public */
export type { SwCategoryDetailStore } from './src/sw-category-detail.store';
