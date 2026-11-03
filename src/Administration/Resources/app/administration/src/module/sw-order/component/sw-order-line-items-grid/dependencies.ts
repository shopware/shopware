/**
 * @sw-package checkout
 *
 * Everything sw-order-line-items-grid needs from outside its mini-module.
 */
import { currencyFilterName } from 'src/app/filter/currency.filter';

/** @private */
export { acl as aclService, orderService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export { LineItemType } from '../../order.types';

/** @private */
export const getCurrencyFilter = () => Shopware.Filter.getByName(currencyFilterName);

/** @private */
export const currency = ((...args: unknown[]) =>
    (Shopware.Utils.format.currency as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.format.currency;

/** @private */
export const get = ((...args: unknown[]) =>
    (Shopware.Utils.get as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.get;
