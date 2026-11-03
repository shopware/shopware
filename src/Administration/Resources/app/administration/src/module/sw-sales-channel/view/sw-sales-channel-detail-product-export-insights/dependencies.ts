/**
 * @sw-package discovery
 *
 * Everything sw-sales-channel-detail-product-export-insights needs from outside its mini-module.
 */
import { currencyFilterName } from 'src/app/filter/currency.filter';
import { sessionStoreId } from 'src/app/store/session.store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);

/** @private */
export const getCurrencyFilter = () => Shopware.Filter.getByName(currencyFilterName);

/** @private */
export const dateWithUserTimezone = ((...args: unknown[]) =>
    (Shopware.Utils.format.dateWithUserTimezone as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.Utils.format.dateWithUserTimezone;

/** @private */
export const toISODate = ((...args: unknown[]) =>
    (Shopware.Utils.format.toISODate as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.format.toISODate;
