/**
 * @sw-package checkout
 *
 * Everything the order bulk edit handler needs from outside its mini-module.
 */
import { swBulkEditStoreId } from 'src/app/store/sw-bulk-edit.store';
import { orderStateMachineService, repositoryFactory } from 'src/app/service-names';

export { default as BulkEditBaseHandler } from '../bulk-edit-base.handler';
export { default as RetryHelper } from 'src/core/helper/retry.helper';
export { default as Criteria } from 'src/core/data/criteria.data';
export { types } from 'src/core/service/util.service';

/** The settings of the bulk edit in progress, such as whether it triggers flows. */
export const useBulkEditStore = () => Shopware.Store.get(swBulkEditStoreId);

export const getOrderStateMachineService = () => Shopware.Service(orderStateMachineService);

export const getRepositoryFactory = () => Shopware.Service(repositoryFactory);

export const getApiContext = () => Shopware.Context.api;
