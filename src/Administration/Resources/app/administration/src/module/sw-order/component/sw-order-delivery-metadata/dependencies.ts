/**
 * @sw-package checkout
 *
 * Everything sw-order-delivery-metadata needs from outside its mini-module.
 */
import { currencyFilterName } from 'src/app/filter/currency.filter';
import { dateFilterName } from 'src/app/filter/date.filter';

/** @private */
export { customSnippetApiService } from 'src/app/service-names';

/** @private */
export const getCurrencyFilter = () => Shopware.Filter.getByName(currencyFilterName);

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);
