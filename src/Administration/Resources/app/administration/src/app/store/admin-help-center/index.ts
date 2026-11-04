/**
 * @sw-package framework
 *
 * The `adminHelpCenter` Pinia store of the Administration.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/adminHelpCenter`.
 */
/**
 * @public
 * @export-namespace stores
 */
export { adminHelpCenterStoreId, useAdminHelpCenterStore } from './src/admin-help-center.store';

/** @public */
export type { AdminHelpCenterStore } from './src/admin-help-center.store';
