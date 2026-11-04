/**
 * @sw-package framework
 *
 * Everything sw-media-modal-renderer needs from outside its mini-module.
 */
import { mediaModalStoreId } from 'src/app/store/media-modal';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export type { MediaModalConfig, SaveMediaModalConfig } from 'src/app/store/media-modal';

/** @private */
export const useMediaModalStore = () => Shopware.Store.get(mediaModalStoreId);
