/**
 * @sw-package inventory
 *
 * Everything sw-product-stream-modal-preview needs from outside its mini-module.
 */
import { currencyFilterName } from 'src/app/filter/currency.filter';
import { stockColorVariantFilterName } from 'src/app/filter/stock-color-variant.filter';

/** @private */
export { default as shuffle } from 'lodash-es/shuffle';
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { productStreamPreviewService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getCurrencyFilter = () => Shopware.Filter.getByName(currencyFilterName);

/** @private */
export const getStockColorVariantFilter = () => Shopware.Filter.getByName(stockColorVariantFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const getAppContext = () => Shopware.Context.app;
