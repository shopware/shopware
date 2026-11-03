/**
 * @sw-package framework
 *
 * Everything sw-bulk-edit-modal needs from outside its mini-module.
 */
import { shopwareAppsStoreId } from 'src/app/store/shopware-apps.store';
import { swBulkEditStoreId } from 'src/app/store/sw-bulk-edit.store';

/** @private */
export const useBulkEditStore = () => Shopware.Store.get(swBulkEditStoreId);

/** @private */
export const useShopwareAppsStore = () => Shopware.Store.get(shopwareAppsStoreId);
