/**
 * @sw-package framework
 */
import * as SwBulkEditStore from './index';
import { swBulkEditStoreOptions } from './private';

Shopware.Store.register(swBulkEditStoreOptions);
Shopware._private.publish('stores', 'swBulkEdit', SwBulkEditStore);
