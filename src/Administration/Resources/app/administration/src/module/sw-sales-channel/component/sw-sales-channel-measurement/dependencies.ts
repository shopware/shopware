/**
 * @sw-package inventory
 *
 * Everything sw-sales-channel-measurement needs from outside its mini-module.
 */
import { errorStoreId } from 'src/app/store/error.store';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);
