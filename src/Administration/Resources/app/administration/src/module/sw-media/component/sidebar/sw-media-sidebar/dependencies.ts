/**
 * @sw-package discovery
 *
 * Everything sw-media-sidebar needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';
import { mediaNameFilterName } from 'src/app/filter/media-name.filter';

/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);

/** @private */
export const getMediaNameFilter = () => Shopware.Filter.getByName(mediaNameFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;
