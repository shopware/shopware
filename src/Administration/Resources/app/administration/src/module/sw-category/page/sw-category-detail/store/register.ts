/**
 * @sw-package inventory
 */
import * as SwCategoryDetailStore from './index';
import { swCategoryDetailStoreOptions } from './private';

Shopware.Store.register(swCategoryDetailStoreOptions);
Shopware._private.publish('stores', 'swCategoryDetail', SwCategoryDetailStore);
