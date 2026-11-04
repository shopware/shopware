/**
 * @sw-package framework
 */
import * as ShopwareAppsStore from './index';
import { shopwareAppsStoreOptions } from './private';

Shopware.Store.register(shopwareAppsStoreOptions);
Shopware._private.publish('stores', 'shopwareApps', ShopwareAppsStore);
