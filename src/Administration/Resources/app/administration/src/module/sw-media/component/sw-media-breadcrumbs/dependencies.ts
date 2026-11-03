/**
 * @sw-package discovery
 *
 * Everything sw-media-breadcrumbs needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';

/** @private */
export { feature as featureService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;
