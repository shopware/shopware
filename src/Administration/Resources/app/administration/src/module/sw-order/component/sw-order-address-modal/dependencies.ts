/**
 * @sw-package checkout
 *
 * Everything sw-order-address-modal needs from outside its mini-module.
 */
import { salutationFilterName } from 'src/app/filter/salutation.filter';
import { errorStoreId } from 'src/app/store/error';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder.mixin';
/** @private */
export {
    acl as aclService,
    feature as featureService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);

/** @private */
export const getSalutationFilter = () => Shopware.Filter.getByName(salutationFilterName);
