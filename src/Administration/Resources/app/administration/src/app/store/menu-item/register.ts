/**
 * @sw-package framework
 */
import * as MenuItemStore from './index';
import { menuItemStoreOptions } from './private';

Shopware.Store.register(menuItemStoreOptions);
Shopware._private.publish('stores', 'menuItem', MenuItemStore);
