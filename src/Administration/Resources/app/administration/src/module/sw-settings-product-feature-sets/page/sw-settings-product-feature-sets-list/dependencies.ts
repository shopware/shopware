/**
 * @sw-package inventory
 *
 * Everything sw-settings-product-feature-sets-list needs from outside its mini-module.
 */
import { contextStoreId } from 'src/app/store/context';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as listingMixin } from 'src/app/mixin/listing';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export { default as FeatureGridTranslationService } from 'src/module/sw-settings-product-feature-sets/service/feature-grid-translation.service';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);
