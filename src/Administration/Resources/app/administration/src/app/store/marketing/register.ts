/**
 * @sw-package framework
 */
import * as MarketingStore from './index';
import { marketingStoreOptions } from './private';

Shopware.Store.register(marketingStoreOptions);
Shopware._private.publish('stores', 'marketing', MarketingStore);
