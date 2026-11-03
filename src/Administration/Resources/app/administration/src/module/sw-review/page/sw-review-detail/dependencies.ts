/**
 * @sw-package after-sales
 *
 * Everything sw-review-detail needs from outside its mini-module.
 */
import { dateFilterName } from 'src/app/filter/date.filter';
import { decodeIdnEmailFilterName } from 'src/app/filter/decode-idn-email.filter';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export {
    acl as aclService,
    customFieldDataProviderService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);

/** @private */
export const getDecodeIdnEmailFilter = () => Shopware.Filter.getByName(decodeIdnEmailFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const publishData = ((...args: unknown[]) =>
    (Shopware.ExtensionAPI.publishData as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.ExtensionAPI.publishData;
