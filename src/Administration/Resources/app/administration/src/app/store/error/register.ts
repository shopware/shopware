/**
 * @sw-package framework
 */
import * as ErrorStore from './index';
import { errorStoreOptions } from './private';

Shopware.Store.register(errorStoreOptions);
Shopware._private.publish('stores', 'error', ErrorStore);
