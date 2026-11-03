/**
 * @sw-package after-sales
 *
 * Everything sw-dashboard-statistics needs from outside its mini-module.
 */
import { currencyFilterName } from 'src/app/filter/currency.filter';
import { dateFilterName } from 'src/app/filter/date.filter';
import { sessionStoreId } from 'src/app/store/session.store';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export {
    acl as aclService,
    repositoryFactory as repositoryFactoryService,
    stateStyleDataProviderService,
} from 'src/app/service-names';

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);

/** @private */
export const getCurrencyFilter = () => Shopware.Filter.getByName(currencyFilterName);

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);

/** @private */
export const getAppContext = () => Shopware.Context.app;

/** @private */
export const currency = ((...args: unknown[]) =>
    (Shopware.Utils.format.currency as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.format.currency;

/** @private */
export const dateWithUserTimezone = ((...args: unknown[]) =>
    (Shopware.Utils.format.dateWithUserTimezone as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.Utils.format.dateWithUserTimezone;

/** @private */
export const toISODate = ((...args: unknown[]) =>
    (Shopware.Utils.format.toISODate as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.format.toISODate;
