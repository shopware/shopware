/**
 * @sw-package checkout
 *
 * Everything the order bulk edit handler needs from outside its mini-module.
 */
import { swBulkEditStoreId } from 'src/app/store/sw-bulk-edit.store';
import { orderStateMachineService, repositoryFactory } from 'src/app/service-names';

/** @private */
export { default as BulkEditBaseHandler } from '../bulk-edit-base.handler';
/** @private */
export { default as RetryHelper } from 'src/core/helper/retry.helper';
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { types } from 'src/core/service/util.service';

/** The settings of the bulk edit in progress, such as whether it triggers flows. @private */
export const useBulkEditStore = () => Shopware.Store.get(swBulkEditStoreId);

/** @private */
export const getOrderStateMachineService = () => Shopware.Service(orderStateMachineService);

/** @private */
export const getRepositoryFactory = () => Shopware.Service(repositoryFactory);

/** @private */
export const getApiContext = () => Shopware.Context.api;
