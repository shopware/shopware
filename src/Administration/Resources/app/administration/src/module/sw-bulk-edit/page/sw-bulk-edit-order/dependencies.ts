/**
 * @sw-package checkout
 *
 * Everything sw-bulk-edit-order needs from outside its mini-module.
 */
import { swBulkEditStoreId } from 'src/app/store/sw-bulk-edit.store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export {
    bulkEditApiFactory as bulkEditApiFactoryService,
    customFieldDataProviderService,
    feature as featureService,
    orderDocumentApiService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';

/** @private */
export const useBulkEditStore = () => Shopware.Store.get(swBulkEditStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const chunk = ((...args: unknown[]) =>
    (Shopware.Utils.array.chunk as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.array.chunk;

/** @private */
export const intersectionBy = ((...args: unknown[]) =>
    (Shopware.Utils.array.intersectionBy as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.Utils.array.intersectionBy;

/** @private */
export const isEmpty = ((...args: unknown[]) =>
    (Shopware.Utils.types.isEmpty as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.types.isEmpty;

/** @private */
export const uniqBy = ((...args: unknown[]) =>
    (Shopware.Utils.array.uniqBy as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.array.uniqBy;
