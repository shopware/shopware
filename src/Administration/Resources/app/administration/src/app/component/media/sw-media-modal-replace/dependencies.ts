/**
 * @sw-package discovery
 *
 * Everything sw-media-modal-replace needs from outside its mini-module.
 */
import { contextStoreId } from 'src/app/store/context.store';

/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export {
    mediaPresignedUploadService,
    mediaService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const createId = (...args: Parameters<typeof Shopware.Utils.createId>) => Shopware.Utils.createId(...args);

/** @private */
export const getNameAndExtensionFromFile = (
    ...args: Parameters<typeof Shopware.Utils.fileReader.getNameAndExtensionFromFile>
) => Shopware.Utils.fileReader.getNameAndExtensionFromFile(...args);
