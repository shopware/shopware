/**
 * @sw-package checkout
 *
 * Everything sw-order-user-card needs from outside its mini-module.
 */
import { currencyFilterName } from 'src/app/filter/currency.filter';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as salutationMixin } from 'src/app/mixin/salutation';
/** @private */
export { customSnippetApiService, orderService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getCurrencyFilter = () => Shopware.Filter.getByName(currencyFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const isFeatureActive = (flag: string) => Shopware.Feature.isActive(flag);
