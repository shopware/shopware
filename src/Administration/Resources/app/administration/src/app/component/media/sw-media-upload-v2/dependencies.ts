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
export const fileSize = ((...args: unknown[]) =>
    (Shopware.Utils.format.fileSize as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.format.fileSize;

/** @private */
export const getNameAndExtensionFromFile = ((...args: unknown[]) =>
    (Shopware.Utils.fileReader.getNameAndExtensionFromFile as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.Utils.fileReader.getNameAndExtensionFromFile;

/** @private */
export const getNameAndExtensionFromUrl = ((...args: unknown[]) =>
    (Shopware.Utils.fileReader.getNameAndExtensionFromUrl as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.Utils.fileReader.getNameAndExtensionFromUrl;

/** @private */
export const warn = ((...args: unknown[]) =>
    (Shopware.Utils.debug.warn as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.debug.warn;
