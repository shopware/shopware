/**
 * @sw-package framework
 *
 * The `mediaModal` Pinia store of the Administration.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/mediaModal`.
 */
/**
 * @public
 * @export-namespace stores
 */
export { mediaModalStoreId, useMediaModalStore } from './src/media-modal.store';

/** @public */
export type { MediaModalConfig, SaveMediaModalConfig, MediaModalStore } from './src/media-modal.store';
