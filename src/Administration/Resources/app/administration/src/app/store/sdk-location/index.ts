/**
 * @sw-package framework
 *
 * The `sdkLocation` Pinia store of the Administration.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/sdkLocation`.
 */
/**
 * @public
 * @export-namespace stores
 */
export { sdkLocationStoreId, useSdkLocationStore } from './src/sdk-location.store';

/** @public */
export type { SdkLocation } from './src/sdk-location.store';
