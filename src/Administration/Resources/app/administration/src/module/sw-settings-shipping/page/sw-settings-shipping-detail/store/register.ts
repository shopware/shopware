/**
 * @sw-package checkout
 */
import * as SwShippingDetailStore from './index';
import { swShippingDetailStoreOptions } from './private';

Shopware.Store.register(swShippingDetailStoreOptions);
Shopware._private.publish('stores', 'swShippingDetail', SwShippingDetailStore);
