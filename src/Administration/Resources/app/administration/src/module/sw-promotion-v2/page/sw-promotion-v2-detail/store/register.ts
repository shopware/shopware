/**
 * @sw-package checkout
 */
import * as SwPromotionDetailStore from './index';
import { swPromotionDetailStoreOptions } from './private';

Shopware.Store.register(swPromotionDetailStoreOptions);
Shopware._private.publish('stores', 'swPromotionDetail', SwPromotionDetailStore);
