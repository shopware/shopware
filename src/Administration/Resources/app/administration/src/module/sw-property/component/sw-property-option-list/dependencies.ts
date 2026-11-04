/**
 * @sw-package inventory
 *
 * Everything sw-property-option-list needs from outside its mini-module.
 */
import { contextStoreId } from 'src/app/store/context';

/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);
