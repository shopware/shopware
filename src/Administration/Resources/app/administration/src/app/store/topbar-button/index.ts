/**
 * @sw-package innovation
 *
 * The `topBarButton` Pinia store of the Administration.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/topBarButton`.
 */
/**
 * @public
 * @shopware-kind stores
 */
export { topBarButtonStoreId, useTopBarButtonStore } from './src/topbar-button.store';

/** @public */
export type { TopBarButtonStore } from './src/topbar-button.store';
