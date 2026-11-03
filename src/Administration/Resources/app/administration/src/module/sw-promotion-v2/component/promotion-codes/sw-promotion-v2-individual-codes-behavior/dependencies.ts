/**
 * @sw-package checkout
 *
 * Everything sw-promotion-v2-individual-codes-behavior needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';
import { dateFilterName } from 'src/app/filter/date.filter';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export {
    acl as aclService,
    promotionCodeApiService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);
