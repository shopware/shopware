/**
 * @sw-package checkout
 *
 * Everything sw-order-nested-line-items-row needs from outside its mini-module.
 */
import { currencyFilterName } from 'src/app/filter/currency.filter';

/** @private */
export const getCurrencyFilter = () => Shopware.Filter.getByName(currencyFilterName);
