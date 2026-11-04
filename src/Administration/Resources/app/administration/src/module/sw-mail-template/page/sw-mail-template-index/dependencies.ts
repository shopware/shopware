/**
 * @sw-package after-sales
 *
 * Everything sw-mail-template-index needs from outside its mini-module.
 */
import { contextStoreId } from 'src/app/store/context';

/** @private */
export { default as listingMixin } from 'src/app/mixin/listing';
/** @private */
export { acl as aclService, feature as featureService } from 'src/app/service-names';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const isFeatureActive = (flag: string) => Shopware.Feature.isActive(flag);
