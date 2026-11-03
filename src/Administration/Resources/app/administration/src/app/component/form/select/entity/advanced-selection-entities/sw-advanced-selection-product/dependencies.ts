/**
 * @sw-package framework
 *
 * Everything sw-advanced-selection-product needs from outside its mini-module.
 */
import { currencyFilterName } from 'src/app/filter/currency.filter';
import { dateFilterName } from 'src/app/filter/date.filter';
import { stockColorVariantFilterName } from 'src/app/filter/stock-color-variant.filter';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getCurrencyFilter = () => Shopware.Filter.getByName(currencyFilterName);

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);

/** @private */
export const getStockColorVariantFilter = () => Shopware.Filter.getByName(stockColorVariantFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;
