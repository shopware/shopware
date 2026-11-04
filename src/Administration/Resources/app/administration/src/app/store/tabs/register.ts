/**
 * @sw-package framework
 */
import * as TabsStore from './index';
import { tabsStoreOptions } from './private';

Shopware.Store.register(tabsStoreOptions);
Shopware._private.publish('stores', 'tabs', TabsStore);
