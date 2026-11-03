/**
 * @sw-package discovery
 *
 * Everything sw-media-modal-folder-settings needs from outside its mini-module.
 */
import { thumbnailSizeFilterName } from 'src/app/filter/thumbnail-size.filter';
import { cacheService } from 'src/app/service-names';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { feature as featureService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';

/** @private */
export const getCacheService = () => Shopware.Service(cacheService);

/** @private */
export const getThumbnailSizeFilter = () => Shopware.Filter.getByName(thumbnailSizeFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const publishData = (...args: Parameters<typeof Shopware.ExtensionAPI.publishData>) =>
    Shopware.ExtensionAPI.publishData(...args);
