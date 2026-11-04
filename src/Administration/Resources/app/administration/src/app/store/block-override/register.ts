/**
 * @sw-package framework
 */
import * as BlockOverrideStore from './index';
import { blockOverrideStoreSetup } from './private';

Shopware.Store.register(BlockOverrideStore.blockOverrideStoreId, blockOverrideStoreSetup);
Shopware._private.publish('stores', 'blockOverride', BlockOverrideStore);
