/**
 * @sw-package framework
 */
import * as SidebarStore from './index';
import { sidebarStoreOptions } from './private';

Shopware.Store.register(sidebarStoreOptions);
Shopware._private.publish('stores', 'sidebar', SidebarStore);
