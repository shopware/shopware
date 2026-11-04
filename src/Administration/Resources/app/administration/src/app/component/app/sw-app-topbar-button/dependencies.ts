/**
 * @sw-package innovation
 *
 * Everything sw-app-topbar-button needs from outside its mini-module.
 */
import { topBarButtonStoreId } from 'src/app/store/topbar-button';

/** @private */
export const useTopBarButtonStore = () => Shopware.Store.get(topBarButtonStoreId);
