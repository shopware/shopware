/**
 * @sw-package inventory
 */
import * as SwProductDetailStore from './index';
import { swProductDetailStoreOptions } from './private';

Shopware.Store.register(swProductDetailStoreOptions);
Shopware._private.publish('stores', 'swProductDetail', SwProductDetailStore);
