/**
 * @sw-package framework
 *
 * The `actionButtons` Pinia store of the Administration.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/actionButtons`.
 */
/**
 * @public
 * @export-namespace stores
 */
export { actionButtonsStoreId, useActionButtonsStore } from './src/action-buttons.store';

/** @public */
export type { ActionButtonsStore } from './src/action-buttons.store';
