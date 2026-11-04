/**
 * @sw-package discovery
 *
 * Everything sw-sales-channel-detail-domains needs from outside its mini-module.
 */
import { unicodeUriFilterName } from 'src/app/filter/unicode-uri';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as ShopwareError } from 'src/core/data/ShopwareError';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getUnicodeUriFilter = () => Shopware.Filter.getByName(unicodeUriFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;
