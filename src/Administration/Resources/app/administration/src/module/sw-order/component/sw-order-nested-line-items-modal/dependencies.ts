/**
 * @sw-package checkout
 *
 * Everything sw-order-nested-line-items-modal needs from outside its mini-module.
 */
import { currencyFilterName } from 'src/app/filter/currency.filter';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getCurrencyFilter = () => Shopware.Filter.getByName(currencyFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;
