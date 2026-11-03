/**
 * @sw-package checkout
 *
 * Everything sw-order-create-details-header needs from outside its mini-module.
 */
import { currencyFilterName } from 'src/app/filter/currency.filter';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';

/** @private */
export const getCurrencyFilter = () => Shopware.Filter.getByName(currencyFilterName);
