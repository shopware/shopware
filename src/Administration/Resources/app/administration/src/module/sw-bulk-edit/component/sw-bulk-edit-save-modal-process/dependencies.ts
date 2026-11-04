/**
 * @sw-package framework
 *
 * Everything sw-bulk-edit-save-modal-process needs from outside its mini-module.
 */
import { swBulkEditStoreId } from 'src/app/store/sw-bulk-edit';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';

/** @private */
export const useBulkEditStore = () => Shopware.Store.get(swBulkEditStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const chunk = ((...args: unknown[]) =>
    (Shopware.Utils.array.chunk as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.array.chunk;
