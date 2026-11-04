/**
 * @sw-package after-sales
 *
 * Everything sw-settings-document-detail needs from outside its mini-module.
 */
import { errorStoreId } from 'src/app/store/error';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as EntityCollection } from 'src/core/data/entity-collection.data';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder';
/** @private */
export {
    acl as aclService,
    customFieldDataProviderService,
    documentV2Service,
    feature as featureService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;
