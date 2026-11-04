/**
 * @sw-package framework
 *
 * Everything sw-bulk-edit-save-modal-confirm needs from outside its mini-module.
 */
import { swBulkEditStoreId } from 'src/app/store/sw-bulk-edit';

/** @private */
export const useBulkEditStore = () => Shopware.Store.get(swBulkEditStoreId);
