/**
 * @sw-package framework
 *
 * Everything sw-app-topbar-sidebar needs from outside its mini-module.
 */
import { sidebarStoreId } from 'src/app/store/sidebar.store';

/** @private */
export const useSidebarStore = () => Shopware.Store.get(sidebarStoreId);
