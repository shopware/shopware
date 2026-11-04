/**
 * @sw-package framework
 */
import * as ModalsStore from './index';
import { modalsStoreOptions } from './private';

Shopware.Store.register(modalsStoreOptions);
Shopware._private.publish('stores', 'modals', ModalsStore);
