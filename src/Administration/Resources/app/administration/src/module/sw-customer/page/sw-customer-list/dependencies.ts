/**
 * @sw-package checkout
 *
 * Everything sw-customer-list needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';
import { decodeIdnEmailFilterName } from 'src/app/filter/decode-idn-email.filter';
import { filterService } from 'src/app/service-names';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as listingMixin } from 'src/app/mixin/listing';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { default as salutationMixin } from 'src/app/mixin/salutation';
/** @private */
export {
    acl as aclService,
    filterFactory as filterFactoryService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';

/** @private */
export const getFilterService = (): ServiceContainer['filterService'] => Shopware.Service(filterService);

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);

/** @private */
export const getDecodeIdnEmailFilter = () => Shopware.Filter.getByName(decodeIdnEmailFilterName);

/** @private */
export const getAppContext = () => Shopware.Context.app;

/** @private */
export const isFeatureActive = (flag: string) => Shopware.Feature.isActive(flag);
