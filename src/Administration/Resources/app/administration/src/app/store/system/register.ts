/**
 * @sw-package framework
 */
import * as SystemStore from './index';
import { systemStoreSetup } from './private';

Shopware.Store.register(SystemStore.systemStoreId, systemStoreSetup);
Shopware._private.publish('stores', 'system', SystemStore);
