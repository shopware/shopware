/**
 * @sw-package framework
 */
import * as AdminMenuStore from './index';
import { adminMenuStoreOptions } from './private';

Shopware.Store.register(adminMenuStoreOptions);
Shopware._private.publish('stores', 'adminMenu', AdminMenuStore);

// Older versions wrote this key on every small-viewport load, so it says nothing about a user choice.
localStorage.removeItem('sw-admin-menu-expanded');
