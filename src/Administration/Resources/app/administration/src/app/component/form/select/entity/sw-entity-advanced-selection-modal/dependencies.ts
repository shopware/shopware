/**
 * @sw-package framework
 *
 * Everything sw-entity-advanced-selection-modal needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as listingMixin } from 'src/app/mixin/listing.mixin';
/** @private */
export {
    acl as aclService,
    filterFactory as filterFactoryService,
    filterService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const debounce = (...args: Parameters<typeof Shopware.Utils.debounce>) => Shopware.Utils.debounce(...args);
