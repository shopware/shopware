/**
 * @sw-package discovery
 *
 * Everything sw-sales-channel-menu needs from outside its mini-module.
 */
import { adminMenuStoreId } from 'src/app/store/admin-menu.store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as FlatTreeHelper } from 'src/core/helper/flattree.helper';
/** @private */
export { default as EventBus } from 'src/core/service/utils/eventBus.utils';
/** @private */
export { acl as aclService, domainLinkService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const useAdminMenuStore = () => Shopware.Store.get(adminMenuStoreId);
