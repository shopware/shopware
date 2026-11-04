/**
 * @sw-package framework
 */
import * as SessionStore from './index';
import { sessionStoreSetup } from './private';

Shopware.Store.register(SessionStore.sessionStoreId, sessionStoreSetup);
Shopware._private.publish('stores', 'session', SessionStore);
