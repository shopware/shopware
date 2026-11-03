/**
 * @sw-package checkout
 *
 * Everything sw-customer-detail-order needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';
import { currencyFilterName } from 'src/app/filter/currency.filter';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);

/** @private */
export const getCurrencyFilter = () => Shopware.Filter.getByName(currencyFilterName);
