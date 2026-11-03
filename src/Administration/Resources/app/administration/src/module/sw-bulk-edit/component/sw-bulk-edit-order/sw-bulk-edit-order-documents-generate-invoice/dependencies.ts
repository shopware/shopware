/**
 * @sw-package checkout
 *
 * Everything sw-bulk-edit-order-documents-generate-invoice needs from outside its mini-module.
 */
import { swBulkEditStoreId } from 'src/app/store/sw-bulk-edit.store';

/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';

/** @private */
export const useBulkEditStore = () => Shopware.Store.get(swBulkEditStoreId);
