/**
 * @sw-package checkout
 *
 * Everything sw-bulk-edit-order-documents-download-documents needs from outside its mini-module.
 */
import { swBulkEditStoreId } from 'src/app/store/sw-bulk-edit';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';

/** @private */
export const useBulkEditStore = () => Shopware.Store.get(swBulkEditStoreId);
