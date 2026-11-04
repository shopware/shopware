/**
 * @sw-package discovery
 *
 * Everything sw-media-modal-replace needs from outside its mini-module.
 */
import { contextStoreId } from 'src/app/store/context';

/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export {
    mediaPresignedUploadService,
    mediaService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const createId = ((...args: unknown[]) =>
    (Shopware.Utils.createId as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.createId;

/** @private */
export const getNameAndExtensionFromFile = ((...args: unknown[]) =>
    (Shopware.Utils.fileReader.getNameAndExtensionFromFile as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.Utils.fileReader.getNameAndExtensionFromFile;
