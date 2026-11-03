/**
 * @sw-package after-sales
 *
 * Everything sw-newsletter-recipient-list needs from outside its mini-module.
 */
import { dateFilterName } from 'src/app/filter/date.filter';
import { decodeIdnEmailFilterName } from 'src/app/filter/decode-idn-email.filter';

/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);

/** @private */
export const getDecodeIdnEmailFilter = () => Shopware.Filter.getByName(decodeIdnEmailFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const isFeatureActive = (flag: string) => Shopware.Feature.isActive(flag);
