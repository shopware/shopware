/**
 * @sw-package checkout
 *
 * Everything sw-bulk-edit-customer needs from outside its mini-module.
 */
import { contextStoreId } from 'src/app/store/context.store';
import { swBulkEditStoreId } from 'src/app/store/sw-bulk-edit.store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export {
    bulkEditApiFactory as bulkEditApiFactoryService,
    customFieldDataProviderService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';

/** @private */
export const useBulkEditStore = () => Shopware.Store.get(swBulkEditStoreId);

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const chunk = ((...args: unknown[]) =>
    (Shopware.Utils.array.chunk as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.array.chunk;

/** @private */
export const cloneDeep = ((...args: unknown[]) =>
    (Shopware.Utils.object.cloneDeep as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.object.cloneDeep;

/** @private */
export const isEmpty = ((...args: unknown[]) =>
    (Shopware.Utils.types.isEmpty as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.types.isEmpty;
