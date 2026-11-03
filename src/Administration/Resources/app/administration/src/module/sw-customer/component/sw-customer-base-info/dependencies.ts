/**
 * @sw-package checkout
 *
 * Everything sw-customer-base-info needs from outside its mini-module.
 */
import { currencyFilterName } from 'src/app/filter/currency.filter';
import { dateFilterName } from 'src/app/filter/date.filter';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';
/** @private */
export { default as CUSTOMER } from '../../constant/sw-customer.constant';
/** @private */
export { default as errorConfig } from '../../error-config.json';

/** @private */
export const getCurrencyFilter = () => Shopware.Filter.getByName(currencyFilterName);

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);

/** @private */
export const getAppContext = () => Shopware.Context.app;
