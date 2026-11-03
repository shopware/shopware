/**
 * @sw-package discovery
 *
 * Everything sw-media-upload-v2 needs from outside its mini-module.
 */
import { mediaNameFilterName } from 'src/app/filter/media-name.filter';
import { contextStoreId } from 'src/app/store/context.store';

/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export {
    feature as featureService,
    fileValidationService,
    mediaPresignedUploadService,
    mediaService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const getMediaNameFilter = () => Shopware.Filter.getByName(mediaNameFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const fileSize = (...args: Parameters<typeof Shopware.Utils.format.fileSize>) =>
    Shopware.Utils.format.fileSize(...args);

/** @private */
export const getNameAndExtensionFromFile = (
    ...args: Parameters<typeof Shopware.Utils.fileReader.getNameAndExtensionFromFile>
) => Shopware.Utils.fileReader.getNameAndExtensionFromFile(...args);

/** @private */
export const getNameAndExtensionFromUrl = (
    ...args: Parameters<typeof Shopware.Utils.fileReader.getNameAndExtensionFromUrl>
) => Shopware.Utils.fileReader.getNameAndExtensionFromUrl(...args);

/** @private */
export const warn = (...args: Parameters<typeof Shopware.Utils.debug.warn>) => Shopware.Utils.debug.warn(...args);
