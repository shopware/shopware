/**
 * @sw-package framework
 */
import * as ContextStore from './index';
import { contextStoreSetup } from './private';

Shopware.Store.register(ContextStore.contextStoreId, contextStoreSetup);
Shopware._private.publish('stores', 'context', ContextStore);
