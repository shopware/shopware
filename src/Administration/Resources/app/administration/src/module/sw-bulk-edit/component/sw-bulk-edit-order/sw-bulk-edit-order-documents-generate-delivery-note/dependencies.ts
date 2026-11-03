/**
 * @sw-package checkout
 *
 * Everything sw-bulk-edit-order-documents-generate-delivery-note needs from outside its mini-module.
 */
import { swBulkEditStoreId } from 'src/app/store/sw-bulk-edit.store';

/** @private */
export const useBulkEditStore = () => Shopware.Store.get(swBulkEditStoreId);
