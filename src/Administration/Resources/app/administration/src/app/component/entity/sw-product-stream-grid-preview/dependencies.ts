/**
 * @sw-package inventory
 *
 * Everything sw-product-stream-grid-preview needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';
import { currencyFilterName } from 'src/app/filter/currency.filter';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { productStreamPreviewService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);

/** @private */
export const getCurrencyFilter = () => Shopware.Filter.getByName(currencyFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const getAppContext = () => Shopware.Context.app;
