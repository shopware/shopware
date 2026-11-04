/**
 * @sw-package checkout
 */
import * as InAppPurchaseCheckoutStore from './index';
import { inAppPurchaseCheckoutStoreOptions } from './private';

Shopware.Store.register(inAppPurchaseCheckoutStoreOptions);
Shopware._private.publish('stores', 'inAppPurchaseCheckout', InAppPurchaseCheckoutStore);
