/**
 * @sw-package after-sales
 *
 * Everything sw-flow-list needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';
import { swFlowStoreId } from '../../../store/flow.store';

/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const useFlowStore = () => Shopware.Store.get(swFlowStoreId);

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;
