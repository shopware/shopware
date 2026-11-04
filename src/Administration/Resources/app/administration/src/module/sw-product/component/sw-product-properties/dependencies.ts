/**
 * @sw-package inventory
 *
 * Everything sw-product-properties needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as EntityCollection } from 'src/core/data/entity-collection.data';
/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export { useSwProductDetailStore as useProductDetailStore } from '../../page/sw-product-detail/store';

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;
